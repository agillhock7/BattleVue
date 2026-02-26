<?php

namespace BattleVue\Services;

use BattleVue\Config;
use RuntimeException;
use Throwable;

class AiTutorService
{
    public function generateTutorReply(string $topicTitle, string $systemPrompt, array $conversation, string $userMessage): array
    {
        $userTokens = $this->estimateTokens($userMessage);

        if (!$this->isConfigured()) {
            $content = $this->fallbackTutorReply($topicTitle, $userMessage);
            $assistantTokens = $this->estimateTokens($content);
            return [
                'content' => $content,
                'usage' => [
                    'prompt_tokens' => $userTokens,
                    'completion_tokens' => $assistantTokens,
                    'total_tokens' => $userTokens + $assistantTokens,
                ],
                'user_tokens' => $userTokens,
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => 'You are teaching topic: ' . $topicTitle . '. Keep responses concise and practical. Ask one follow-up question at the end.'],
            ['role' => 'system', 'content' => 'Formatting rules: use Markdown. Prefer this structure when possible: 1) short concept, 2) practical example, 3) quick exercise, 4) one follow-up question. Use fenced code blocks for code and bullet lists for steps.'],
        ];

        foreach ($conversation as $entry) {
            $role = (string) ($entry['role'] ?? 'user');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role' => $role,
                'content' => (string) ($entry['content'] ?? ''),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $response = $this->chatCompletions($messages, 0.6, 450, null);
        $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            $content = $this->fallbackTutorReply($topicTitle, $userMessage);
        }

        $usage = $response['usage'] ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? $userTokens);
        $completionTokens = (int) ($usage['completion_tokens'] ?? $this->estimateTokens($content));
        $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => max(0, $promptTokens),
                'completion_tokens' => max(0, $completionTokens),
                'total_tokens' => max(0, $totalTokens),
            ],
            'user_tokens' => $userTokens,
        ];
    }

    public function generateCheckpointQuiz(string $topicTitle, string $systemPrompt, array $conversation, int $tier): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackQuiz($topicTitle, $tier);
        }

        $transcript = [];
        foreach ($conversation as $entry) {
            $role = (string) ($entry['role'] ?? 'user');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $transcript[] = strtoupper($role) . ': ' . trim((string) ($entry['content'] ?? ''));
        }

        $prompt = "Create a tier {$tier} learning checkpoint quiz for topic '{$topicTitle}'.\n"
            . "Base it on this transcript:\n"
            . implode("\n", array_slice($transcript, -14))
            . "\n\nReturn STRICT JSON with this exact structure:"
            . "{\"title\":string,\"instructions\":string,\"questions\":[{\"question\":string,\"choices\":[string,string,string,string],\"answer_index\":0-3,\"explanation\":string}]}"
            . "\nUse exactly 3 questions.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => 'You are generating assessment quizzes. Be accurate, avoid ambiguity, and always output valid JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        try {
            $response = $this->chatCompletions($messages, 0.4, 700, ['type' => 'json_object']);
            $raw = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $this->isValidQuiz($decoded)) {
                return $decoded;
            }
        } catch (Throwable $e) {
            // Fall through to fallback quiz.
        }

        return $this->fallbackQuiz($topicTitle, $tier);
    }

    public function generateSuggestedPrompts(string $topicTitle, string $systemPrompt, array $conversation): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackSuggestedPrompts($topicTitle);
        }

        $transcript = [];
        foreach ($conversation as $entry) {
            $role = (string) ($entry['role'] ?? 'user');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $transcript[] = strtoupper($role) . ': ' . trim((string) ($entry['content'] ?? ''));
        }

        $prompt = "Based on this learning conversation for '{$topicTitle}', produce 3 suggested NEXT USER INPUTS to continue in the learner's current direction.\n"
            . "Conversation:\n" . implode("\n", array_slice($transcript, -16))
            . "\n\nReturn STRICT JSON with this shape: {\"suggested_prompts\":[string,string,string]}\n"
            . "Requirements: each prompt must be concise, practical, and progressively deeper.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => 'You generate guided next prompts for learners. Output must be valid JSON only.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        try {
            $response = $this->chatCompletions($messages, 0.5, 250, ['type' => 'json_object']);
            $raw = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && is_array($decoded['suggested_prompts'] ?? null)) {
                $clean = array_values(array_filter(array_map(static fn($p) => trim((string) $p), $decoded['suggested_prompts']), static fn($p) => $p !== ''));
                if (count($clean) > 0) {
                    return array_slice($clean, 0, 6);
                }
            }
        } catch (Throwable $e) {
            // Fall through to fallback.
        }

        return $this->fallbackSuggestedPrompts($topicTitle);
    }

    private function chatCompletions(array $messages, float $temperature, int $maxTokens, ?array $responseFormat): array
    {
        $apiKey = (string) Config::get('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key not configured.');
        }

        $payload = [
            'model' => (string) Config::get('OPENAI_MODEL', 'gpt-4.1'),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($responseFormat !== null) {
            $payload['response_format'] = $responseFormat;
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('OpenAI requests require PHP cURL extension.');
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize OpenAI request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => (int) Config::get('OPENAI_TIMEOUT_SECONDS', 30),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('OpenAI request failed: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? 'OpenAI API error')
                : 'OpenAI API error';
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    private function fallbackTutorReply(string $topicTitle, string $userMessage): string
    {
        $trimmed = trim($userMessage);
        return "Great focus on {$topicTitle}. You said: '{$trimmed}'. Let's break this into a practical mini-plan: (1) core concept, (2) quick example, (3) one hands-on task. Which part do you want to do first?";
    }

    private function fallbackQuiz(string $topicTitle, int $tier): array
    {
        return [
            'title' => "{$topicTitle} Checkpoint Tier {$tier}",
            'instructions' => 'Answer each question based on your current understanding.',
            'questions' => [
                [
                    'question' => "Which approach best improves your mastery of {$topicTitle}?",
                    'choices' => [
                        'Passive reading only',
                        'Short feedback loops with practical exercises',
                        'Skipping difficult concepts',
                        'Memorizing without testing',
                    ],
                    'answer_index' => 1,
                    'explanation' => 'Practical exercises with feedback produce stronger retention.',
                ],
                [
                    'question' => 'What should you do when a concept is unclear?',
                    'choices' => [
                        'Ignore it',
                        'Switch topics immediately',
                        'Ask a focused follow-up and test with a small example',
                        'Copy code without understanding',
                    ],
                    'answer_index' => 2,
                    'explanation' => 'Focused follow-ups plus small experiments accelerate learning.',
                ],
                [
                    'question' => 'What is the best checkpoint habit?',
                    'choices' => [
                        'Avoid quizzes',
                        'Use checkpoints to identify gaps and reinforce concepts',
                        'Only do theory',
                        'Only do implementation',
                    ],
                    'answer_index' => 1,
                    'explanation' => 'Checkpoints are for gap detection and reinforcement.',
                ],
            ],
        ];
    }

    private function fallbackSuggestedPrompts(string $topicTitle): array
    {
        return [
            'Can you break this down into simpler steps for a beginner in ' . $topicTitle . '?',
            'Give me a practical mini exercise I can do next.',
            'What is the most common mistake here and how do I avoid it?',
        ];
    }

    private function isValidQuiz(array $quiz): bool
    {
        if (!isset($quiz['title'], $quiz['instructions'], $quiz['questions']) || !is_array($quiz['questions'])) {
            return false;
        }

        if (count($quiz['questions']) < 1) {
            return false;
        }

        foreach ($quiz['questions'] as $question) {
            if (!is_array($question)) {
                return false;
            }
            if (!isset($question['question'], $question['choices'], $question['answer_index'], $question['explanation'])) {
                return false;
            }
            if (!is_array($question['choices']) || count($question['choices']) !== 4) {
                return false;
            }
            $answerIndex = (int) $question['answer_index'];
            if ($answerIndex < 0 || $answerIndex > 3) {
                return false;
            }
        }

        return true;
    }

    private function isConfigured(): bool
    {
        return trim((string) Config::get('OPENAI_API_KEY', '')) !== '';
    }

    private function estimateTokens(string $text): int
    {
        $length = strlen(trim($text));
        if ($length === 0) {
            return 0;
        }
        return max(1, (int) ceil($length / 4));
    }
}
