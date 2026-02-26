<?php

namespace BattleVue\Repos;

use PDO;

class NotificationRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, type, title, body, data_json, is_read, created_at, read_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':user_id' => $userId]);
        return array_map(function (array $row) {
            $row['data'] = $row['data_json'] ? json_decode($row['data_json'], true) : null;
            unset($row['data_json']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function markRead(int $userId, array $ids): void
    {
        if (count($ids) === 0) {
            $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0');
            $stmt->execute([':user_id' => $userId]);
            return;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$userId], $ids);
        $stmt->execute($params);
    }

    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, title, body, data_json)
             VALUES (:user_id, :type, :title, :body, :data_json)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':body' => $body,
            ':data_json' => $data ? json_encode($data) : null,
        ]);
    }
}
