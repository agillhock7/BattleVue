<?php

namespace BattleVue\Repos;

use PDO;

class QuestRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function getTracks(): array
    {
        $stmt = $this->db->query('SELECT id, slug, title, description, sort_order FROM tracks WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll();
    }

    public function getQuestsByTrack(?string $trackSlug): array
    {
        if ($trackSlug) {
            $stmt = $this->db->prepare(
                'SELECT q.id, q.track_id, t.slug AS track_slug, q.slug, q.title, q.description, q.difficulty, q.sort_order
                 FROM quests q
                 INNER JOIN tracks t ON t.id = q.track_id
                 WHERE q.is_active = 1 AND t.slug = :track_slug
                 ORDER BY q.sort_order ASC, q.id ASC'
            );
            $stmt->execute([':track_slug' => $trackSlug]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query(
            'SELECT q.id, q.track_id, t.slug AS track_slug, q.slug, q.title, q.description, q.difficulty, q.sort_order
             FROM quests q
             INNER JOIN tracks t ON t.id = q.track_id
             WHERE q.is_active = 1
             ORDER BY q.sort_order ASC, q.id ASC'
        );
        return $stmt->fetchAll();
    }

    public function getQuest(int $questId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT q.id, q.track_id, q.slug, q.title, q.description, q.difficulty,
                    t.slug AS track_slug, t.title AS track_title
             FROM quests q
             INNER JOIN tracks t ON t.id = q.track_id
             WHERE q.id = :id AND q.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $questId]);
        $quest = $stmt->fetch();
        if (!$quest) {
            return null;
        }

        $steps = $this->db->prepare('SELECT id, step_index, step_type, payload_json, required FROM quest_steps WHERE quest_id = :quest_id ORDER BY step_index ASC');
        $steps->execute([':quest_id' => $questId]);
        $quest['steps'] = array_map(function (array $row) {
            $row['payload'] = json_decode($row['payload_json'], true);
            unset($row['payload_json']);
            return $row;
        }, $steps->fetchAll());

        return $quest;
    }

    public function getCompletion(int $userId, int $questId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM quest_completions WHERE user_id = :user_id AND quest_id = :quest_id LIMIT 1');
        $stmt->execute([':user_id' => $userId, ':quest_id' => $questId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertCompletion(int $userId, int $questId, array $progress, string $status = 'in_progress'): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quest_completions (user_id, quest_id, status, progress_json)
             VALUES (:user_id, :quest_id, :status, :progress)
             ON DUPLICATE KEY UPDATE status = VALUES(status), progress_json = VALUES(progress_json), updated_at = NOW()'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':quest_id' => $questId,
            ':status' => $status,
            ':progress' => json_encode($progress),
        ]);
    }

    public function markCompleted(int $userId, int $questId, array $progress): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quest_completions (user_id, quest_id, status, progress_json, completed_at)
             VALUES (:user_id, :quest_id, "completed", :progress, NOW())
             ON DUPLICATE KEY UPDATE status = "completed", progress_json = VALUES(progress_json), completed_at = NOW(), updated_at = NOW()'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':quest_id' => $questId,
            ':progress' => json_encode($progress),
        ]);
    }

    public function rewardPacksForQuest(int $questId): array
    {
        $stmt = $this->db->prepare('SELECT rewards_json FROM reward_packs WHERE quest_id = :quest_id');
        $stmt->execute([':quest_id' => $questId]);
        $rows = $stmt->fetchAll();
        $rewards = [];
        foreach ($rows as $row) {
            $pack = json_decode($row['rewards_json'], true);
            if (is_array($pack)) {
                $rewards = array_merge($rewards, $pack);
            }
        }
        return $rewards;
    }

    public function addInventoryBySlug(int $userId, string $itemSlug, int $quantity): void
    {
        $stmt = $this->db->prepare('SELECT id FROM items WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $itemSlug]);
        $item = $stmt->fetch();
        if (!$item) {
            return;
        }

        $upsert = $this->db->prepare(
            'INSERT INTO user_inventory (user_id, item_id, quantity)
             VALUES (:user_id, :item_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()'
        );
        $upsert->execute([
            ':user_id' => $userId,
            ':item_id' => $item['id'],
            ':quantity' => $quantity,
        ]);
    }
}
