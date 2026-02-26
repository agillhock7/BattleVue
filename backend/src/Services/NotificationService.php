<?php

namespace BattleVue\Services;

use BattleVue\Repos\NotificationRepo;

class NotificationService
{
    public function __construct(private NotificationRepo $repo)
    {
    }

    public function list(int $userId): array
    {
        return $this->repo->listForUser($userId);
    }

    public function read(int $userId, array $ids): void
    {
        $this->repo->markRead($userId, $ids);
    }
}
