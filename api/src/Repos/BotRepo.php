<?php

namespace BattleVue\Repos;

use PDO;

class BotRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function inventory(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.slug, i.name, i.item_type, i.rarity, i.metadata_json, ui.quantity
             FROM user_inventory ui
             INNER JOIN items i ON i.id = ui.item_id
             WHERE ui.user_id = :user_id
             ORDER BY i.item_type ASC, i.name ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return array_map(function (array $row) {
            $row['metadata'] = json_decode($row['metadata_json'], true);
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function createBlueprint(int $userId, array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO bot_blueprints (user_id, name, chassis, lane_pref, modules_json, stats_json)
             VALUES (:user_id, :name, :chassis, :lane_pref, :modules_json, :stats_json)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $payload['name'],
            ':chassis' => $payload['chassis'],
            ':lane_pref' => $payload['lane_pref'] ?? 'adaptive',
            ':modules_json' => json_encode($payload['modules'] ?? []),
            ':stats_json' => json_encode($payload['stats'] ?? []),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateBlueprint(int $userId, int $id, array $payload): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE bot_blueprints
             SET name = :name,
                 chassis = :chassis,
                 lane_pref = :lane_pref,
                 modules_json = :modules_json,
                 stats_json = :stats_json,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':name' => $payload['name'],
            ':chassis' => $payload['chassis'],
            ':lane_pref' => $payload['lane_pref'] ?? 'adaptive',
            ':modules_json' => json_encode($payload['modules'] ?? []),
            ':stats_json' => json_encode($payload['stats'] ?? []),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listBlueprints(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bot_blueprints WHERE user_id = :user_id ORDER BY updated_at DESC');
        $stmt->execute([':user_id' => $userId]);
        return array_map(function (array $row) {
            $row['modules'] = json_decode($row['modules_json'], true);
            $row['stats'] = json_decode($row['stats_json'], true);
            unset($row['modules_json'], $row['stats_json']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function createRuleset(int $userId, array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO bot_rulesets (user_id, name, rules_json)
             VALUES (:user_id, :name, :rules_json)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $payload['name'],
            ':rules_json' => json_encode($payload['rules'] ?? []),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateRuleset(int $userId, int $id, array $payload): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE bot_rulesets
             SET name = :name,
                 rules_json = :rules_json,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':name' => $payload['name'],
            ':rules_json' => json_encode($payload['rules'] ?? []),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listRulesets(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bot_rulesets WHERE user_id = :user_id ORDER BY updated_at DESC');
        $stmt->execute([':user_id' => $userId]);
        return array_map(function (array $row) {
            $row['rules'] = json_decode($row['rules_json'], true);
            unset($row['rules_json']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function getBlueprint(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bot_blueprints WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['modules'] = json_decode($row['modules_json'], true);
        $row['stats'] = json_decode($row['stats_json'], true);
        unset($row['modules_json'], $row['stats_json']);
        return $row;
    }

    public function getRuleset(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bot_rulesets WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['rules'] = json_decode($row['rules_json'], true);
        unset($row['rules_json']);
        return $row;
    }
}
