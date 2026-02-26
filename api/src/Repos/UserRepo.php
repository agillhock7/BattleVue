<?php

namespace BattleVue\Repos;

use PDO;

class UserRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, display_name, avatar_url, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function search(string $query, int $excludeUserId, int $limit = 20): array
    {
        $term = '%' . $query . '%';
        $stmt = $this->db->prepare(
            'SELECT id, username, display_name, avatar_url
             FROM users
             WHERE id <> :exclude_id
               AND (username LIKE :term OR display_name LIKE :term)
             ORDER BY username ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':exclude_id' => $excludeUserId, ':term' => $term]);
        return $stmt->fetchAll();
    }
}
