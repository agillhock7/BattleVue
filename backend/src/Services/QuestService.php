<?php

namespace BattleVue\Services;

use BattleVue\Repos\QuestRepo;
use RuntimeException;

class QuestService
{
    public function __construct(private QuestRepo $questRepo)
    {
    }

    public function tracks(): array
    {
        return $this->questRepo->getTracks();
    }

    public function quests(?string $trackSlug): array
    {
        return $this->questRepo->getQuestsByTrack($trackSlug);
    }

    public function quest(int $questId): array
    {
        $quest = $this->questRepo->getQuest($questId);
        if (!$quest) {
            throw new RuntimeException('Quest not found.');
        }
        return $quest;
    }

    public function submitStep(int $userId, int $questId, int $stepIndex, array $submission): array
    {
        $quest = $this->questRepo->getQuest($questId);
        if (!$quest) {
            throw new RuntimeException('Quest not found.');
        }

        $steps = $quest['steps'];
        $matched = null;
        foreach ($steps as $step) {
            if ((int) $step['step_index'] === $stepIndex) {
                $matched = $step;
                break;
            }
        }
        if (!$matched) {
            throw new RuntimeException('Step not found.');
        }

        $isValid = $this->validateSubmission($matched, $submission);
        if (!$isValid) {
            throw new RuntimeException('Submission did not pass validation.');
        }

        $completion = $this->questRepo->getCompletion($userId, $questId);
        $progress = $completion ? json_decode($completion['progress_json'], true) : [];
        if (!is_array($progress)) {
            $progress = [];
        }

        $progress[(string) $stepIndex] = [
            'completed' => true,
            'submitted_at' => gmdate('c'),
        ];

        $status = 'in_progress';
        $requiredStepIndexes = array_values(array_map(static fn($s) => (int) $s['step_index'], array_filter($steps, static fn($s) => (int) $s['required'] === 1)));
        $done = true;
        foreach ($requiredStepIndexes as $requiredStepIndex) {
            if (empty($progress[(string) $requiredStepIndex]['completed'])) {
                $done = false;
                break;
            }
        }
        if ($done) {
            $status = 'completed';
            $this->questRepo->markCompleted($userId, $questId, $progress);
            $this->applyRewards($userId, $questId);
        } else {
            $this->questRepo->upsertCompletion($userId, $questId, $progress, $status);
        }

        return ['status' => $status, 'progress' => $progress];
    }

    public function completeQuest(int $userId, int $questId): array
    {
        $quest = $this->questRepo->getQuest($questId);
        if (!$quest) {
            throw new RuntimeException('Quest not found.');
        }

        $completion = $this->questRepo->getCompletion($userId, $questId);
        $progress = $completion ? json_decode($completion['progress_json'], true) : [];
        if (!is_array($progress)) {
            $progress = [];
        }

        foreach ($quest['steps'] as $step) {
            if ((int) $step['required'] === 1 && empty($progress[(string) $step['step_index']]['completed'])) {
                throw new RuntimeException('Not all required steps are complete.');
            }
        }

        $this->questRepo->markCompleted($userId, $questId, $progress);
        $this->applyRewards($userId, $questId);
        return ['status' => 'completed'];
    }

    private function validateSubmission(array $step, array $submission): bool
    {
        $payload = $step['payload'] ?? [];
        $type = $step['step_type'];

        if ($type === 'quiz') {
            return (string) ($submission['answer'] ?? '') === (string) ($payload['answer'] ?? '');
        }

        if ($type === 'checklist') {
            return !empty($submission['checked']);
        }

        if ($type === 'snippet') {
            return mb_strlen(trim((string) ($submission['code'] ?? ''))) >= 5;
        }

        return true;
    }

    private function applyRewards(int $userId, int $questId): void
    {
        $rewards = $this->questRepo->rewardPacksForQuest($questId);
        foreach ($rewards as $reward) {
            $slug = (string) ($reward['item_slug'] ?? '');
            $quantity = max(1, (int) ($reward['quantity'] ?? 1));
            if ($slug !== '') {
                $this->questRepo->addInventoryBySlug($userId, $slug, $quantity);
            }
        }
    }
}
