<?php

namespace BattleVue\Services;

use BattleVue\Repos\LearningRepo;
use RuntimeException;

class LearningService
{
    public function __construct(
        private LearningRepo $repo,
        private AiTutorService $aiTutorService
    ) {
    }

    public function listTopics(int $userId): array
    {
        return $this->repo->listTopics($userId);
    }

    public function createCustomTopic(int $userId, string $title, string $description): array
    {
        $title = trim($title);
        $description = trim($description);

        if ($title === '' || mb_strlen($title) < 3 || mb_strlen($title) > 140) {
            throw new RuntimeException('Custom topic title must be 3-140 characters.');
        }
        if ($description === '' || mb_strlen($description) < 10 || mb_strlen($description) > 1000) {
            throw new RuntimeException('Custom topic description must be 10-1000 characters.');
        }

        $systemPrompt = $this->buildCustomTopicSystemPrompt($title, $description);
        $topicId = $this->repo->createCustomTopic($userId, $title, $description, $systemPrompt);
        return [
            'topic_id' => $topicId,
            'title' => $title,
        ];
    }

    public function startSession(int $userId, int $topicId): array
    {
        $topic = $this->repo->findTopicByIdForUser($topicId, $userId);
        if (!$topic) {
            throw new RuntimeException('Learning topic not found.');
        }

        $sessionId = $this->repo->createSession($userId, $topicId);
        return $this->getSession($userId, $sessionId);
    }

    public function getSession(int $userId, int $sessionId): array
    {
        $session = $this->repo->getSession($sessionId, $userId);
        if (!$session) {
            throw new RuntimeException('Learning session not found.');
        }

        $messages = $this->repo->listMessages($sessionId, 200);
        $checkpoints = $this->repo->listCheckpoints($sessionId);
        $pending = $this->repo->getPendingCheckpoint($sessionId);
        $botPoints = $this->repo->getBotPoints($userId);

        $nextTier = max(1, ((int) $session['last_checkpoint_tier']) + 1);
        $nextTarget = $this->tokenTargetForTier($nextTier);
        $remaining = max(0, $nextTarget - (int) $session['cumulative_user_tokens']);

        return [
            'session' => [
                'id' => (int) $session['id'],
                'topic_id' => (int) $session['topic_id'],
                'topic_slug' => $session['topic_slug'],
                'topic_title' => $session['topic_title'],
                'topic_description' => $session['topic_description'],
                'status' => $session['status'],
                'cumulative_user_tokens' => (int) $session['cumulative_user_tokens'],
                'cumulative_model_tokens' => (int) $session['cumulative_model_tokens'],
                'cumulative_total_tokens' => (int) $session['cumulative_total_tokens'],
                'last_checkpoint_tier' => (int) $session['last_checkpoint_tier'],
                'next_checkpoint_tier' => $nextTier,
                'next_checkpoint_token_target' => $nextTarget,
                'tokens_to_next_checkpoint' => $remaining,
                'bot_points' => $botPoints,
            ],
            'messages' => $messages,
            'checkpoints' => $checkpoints,
            'pending_checkpoint' => $pending ? $this->normalizePendingCheckpoint($pending) : null,
        ];
    }

    public function sendMessage(int $userId, int $sessionId, string $content): array
    {
        $session = $this->repo->getSession($sessionId, $userId);
        if (!$session) {
            throw new RuntimeException('Learning session not found.');
        }

        $content = trim($content);
        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 4000) {
            throw new RuntimeException('Message must be 2-4000 characters.');
        }

        $conversation = $this->repo->listRecentMessagesForPrompt($sessionId, 20);
        $assistant = $this->aiTutorService->generateTutorReply(
            (string) $session['topic_title'],
            (string) $session['system_prompt'],
            $conversation,
            $content
        );

        $userTokens = (int) ($assistant['user_tokens'] ?? 0);
        $promptTokens = (int) ($assistant['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($assistant['usage']['completion_tokens'] ?? 0);
        $totalTokens = (int) ($assistant['usage']['total_tokens'] ?? ($promptTokens + $completionTokens));

        $this->repo->addMessage($sessionId, 'user', $content, $userTokens, 0, $userTokens);
        $this->repo->addMessage(
            $sessionId,
            'assistant',
            (string) $assistant['content'],
            max(1, $completionTokens),
            max(0, $completionTokens),
            max(1, $totalTokens)
        );
        $this->repo->addSessionTokens(
            $sessionId,
            $userTokens,
            max(0, $completionTokens),
            max(1, $totalTokens)
        );

        $checkpointCreated = false;
        $checkpoint = $this->repo->getPendingCheckpoint($sessionId);
        if (!$checkpoint) {
            $updatedSession = $this->repo->getSession($sessionId, $userId);
            if (!$updatedSession) {
                throw new RuntimeException('Learning session no longer exists.');
            }

            $nextTier = max(1, ((int) $updatedSession['last_checkpoint_tier']) + 1);
            $target = $this->tokenTargetForTier($nextTier);
            if ((int) $updatedSession['cumulative_user_tokens'] >= $target) {
                $quiz = $this->aiTutorService->generateCheckpointQuiz(
                    (string) $updatedSession['topic_title'],
                    (string) $updatedSession['system_prompt'],
                    $this->repo->listRecentMessagesForPrompt($sessionId, 24),
                    $nextTier
                );

                $this->repo->createCheckpoint($sessionId, $nextTier, $quiz);
                $checkpointCreated = true;
            }
        }

        return [
            'assistant_message' => (string) $assistant['content'],
            'usage' => [
                'user_tokens' => $userTokens,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
            ],
            'checkpoint_created' => $checkpointCreated,
            'state' => $this->getSession($userId, $sessionId),
        ];
    }

    public function submitCheckpoint(int $userId, int $sessionId, array $answers): array
    {
        $session = $this->repo->getSession($sessionId, $userId);
        if (!$session) {
            throw new RuntimeException('Learning session not found.');
        }

        $checkpoint = $this->repo->getPendingCheckpoint($sessionId);
        if (!$checkpoint) {
            throw new RuntimeException('No pending checkpoint for this session.');
        }

        $quiz = json_decode((string) ($checkpoint['quiz_json'] ?? ''), true);
        if (!is_array($quiz) || !is_array($quiz['questions'] ?? null)) {
            throw new RuntimeException('Checkpoint quiz is invalid.');
        }

        $normalizedAnswers = array_map('intval', $answers);
        $questions = $quiz['questions'];
        $total = count($questions);
        if ($total <= 0) {
            throw new RuntimeException('Checkpoint has no questions.');
        }

        $correct = 0;
        foreach ($questions as $idx => $question) {
            $expected = (int) ($question['answer_index'] ?? -1);
            $actual = (int) ($normalizedAnswers[$idx] ?? -999);
            if ($expected === $actual) {
                $correct++;
            }
        }

        $scorePercent = (int) floor(($correct / $total) * 100);
        $passed = $scorePercent >= (int) $this->configInt('LEARN_CHECKPOINT_PASS_PERCENT', 70);
        $tier = (int) $checkpoint['tier'];
        $awardedPoints = $passed ? $this->pointsForTier($tier) : 0;

        $this->repo->submitCheckpoint((int) $checkpoint['id'], $normalizedAnswers, $scorePercent, $passed, $awardedPoints);

        if ($passed) {
            $this->repo->setLastCheckpointTier($sessionId, $tier);
            $this->repo->addRewardPoints($userId, $awardedPoints, 'learning_checkpoint', [
                'session_id' => $sessionId,
                'checkpoint_id' => (int) $checkpoint['id'],
                'tier' => $tier,
                'score_percent' => $scorePercent,
            ]);
        }

        return [
            'passed' => $passed,
            'score_percent' => $scorePercent,
            'awarded_points' => $awardedPoints,
            'state' => $this->getSession($userId, $sessionId),
        ];
    }

    private function normalizePendingCheckpoint(array $checkpoint): array
    {
        return [
            'id' => (int) $checkpoint['id'],
            'tier' => (int) $checkpoint['tier'],
            'quiz' => json_decode((string) $checkpoint['quiz_json'], true),
            'created_at' => $checkpoint['created_at'],
        ];
    }

    private function tokenTargetForTier(int $tier): int
    {
        $base = $this->configInt('LEARN_CHECKPOINT_BASE_TOKENS', 180);
        $step = $this->configInt('LEARN_CHECKPOINT_STEP_TOKENS', 120);
        return $base + max(0, $tier - 1) * $step;
    }

    private function pointsForTier(int $tier): int
    {
        $base = $this->configInt('LEARN_POINTS_BASE', 50);
        $step = $this->configInt('LEARN_POINTS_STEP', 25);
        return $base + max(0, $tier - 1) * $step;
    }

    private function configInt(string $key, int $default): int
    {
        return (int) \BattleVue\Config::get($key, $default);
    }

    private function buildCustomTopicSystemPrompt(string $title, string $description): string
    {
        return 'You are a specialist tutor for the user-selected learning topic "' . $title . '". '
            . 'Topic scope: ' . $description . '. '
            . 'Teach through interest-based prompts and practical examples. '
            . 'Keep tone clear and supportive. Ask one thoughtful follow-up at the end of each response.';
    }
}
