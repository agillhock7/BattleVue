<?php

namespace BattleVue\Repos;

use PDO;

class MatchRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function findOpenQueueMatch(int $userId, string $mode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM matches m
             WHERE m.status = "queued"
               AND m.mode = :mode
               AND EXISTS (
                 SELECT 1
                 FROM match_players mp
                 WHERE mp.match_id = m.id
                   AND mp.user_id <> :user_id
               )
               AND (
                 SELECT COUNT(*)
                 FROM match_players mp2
                 WHERE mp2.match_id = m.id
               ) = 1
             ORDER BY m.created_at ASC
             LIMIT 1'
        );
        $stmt->execute([':mode' => $mode, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function userHasActiveMatch(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT m.id
             FROM matches m
             INNER JOIN match_players mp ON mp.match_id = m.id
             WHERE mp.user_id = :user_id
               AND m.status IN ("queued", "awaiting_submission", "simulating")
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        return (bool) $stmt->fetch();
    }

    public function createMatch(int $creatorUserId, string $mode, int $seed, string $simVersion): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO matches (mode, status, seed, simulator_version, created_by_user_id)
             VALUES (:mode, "queued", :seed, :sim, :creator)'
        );
        $stmt->execute([
            ':mode' => $mode,
            ':seed' => $seed,
            ':sim' => $simVersion,
            ':creator' => $creatorUserId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function addPlayer(int $matchId, int $userId, int $slot): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO match_players (match_id, user_id, slot_order)
             VALUES (:match_id, :user_id, :slot_order)'
        );
        $stmt->execute([':match_id' => $matchId, ':user_id' => $userId, ':slot_order' => $slot]);
    }

    public function setAwaitingSubmission(int $matchId): void
    {
        $stmt = $this->db->prepare('UPDATE matches SET status = "awaiting_submission", started_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $matchId]);
    }

    public function getMatchForUser(int $matchId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM matches m
             INNER JOIN match_players mp ON mp.match_id = m.id
             WHERE m.id = :id AND mp.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $matchId, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getMatchById(int $matchId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $matchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getPlayers(int $matchId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM match_players WHERE match_id = :match_id ORDER BY slot_order ASC');
        $stmt->execute([':match_id' => $matchId]);
        return array_map(function (array $row) {
            if (!empty($row['blueprint_snapshot_json'])) {
                $row['blueprint_snapshot'] = json_decode($row['blueprint_snapshot_json'], true);
            }
            if (!empty($row['ruleset_snapshot_json'])) {
                $row['ruleset_snapshot'] = json_decode($row['ruleset_snapshot_json'], true);
            }
            return $row;
        }, $stmt->fetchAll());
    }

    public function submitPlayerLoadout(int $matchId, int $userId, int $blueprintId, int $rulesetId, array $blueprint, array $ruleset): void
    {
        $stmt = $this->db->prepare(
            'UPDATE match_players
             SET blueprint_id = :blueprint_id,
                 ruleset_id = :ruleset_id,
                 blueprint_snapshot_json = :blueprint_snapshot,
                 ruleset_snapshot_json = :ruleset_snapshot,
                 submitted_at = NOW()
             WHERE match_id = :match_id AND user_id = :user_id'
        );
        $stmt->execute([
            ':match_id' => $matchId,
            ':user_id' => $userId,
            ':blueprint_id' => $blueprintId,
            ':ruleset_id' => $rulesetId,
            ':blueprint_snapshot' => json_encode($blueprint),
            ':ruleset_snapshot' => json_encode($ruleset),
        ]);
    }

    public function areAllSubmitted(int $matchId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submitted
             FROM match_players
             WHERE match_id = :match_id'
        );
        $stmt->execute([':match_id' => $matchId]);
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0) > 0 && (int) ($row['total'] ?? 0) === (int) ($row['submitted'] ?? 0);
    }

    public function markSimulating(int $matchId): void
    {
        $stmt = $this->db->prepare('UPDATE matches SET status = "simulating" WHERE id = :id');
        $stmt->execute([':id' => $matchId]);
    }

    public function writeEvents(int $matchId, array $events): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO match_events (match_id, tick, event_type, payload_json)
             VALUES (:match_id, :tick, :event_type, :payload_json)'
        );

        foreach ($events as $event) {
            $stmt->execute([
                ':match_id' => $matchId,
                ':tick' => (int) ($event['tick'] ?? 0),
                ':event_type' => (string) ($event['event_type'] ?? 'event'),
                ':payload_json' => json_encode($event['payload'] ?? []),
            ]);
        }
    }

    public function finalizeMatch(int $matchId, ?int $winnerUserId, array $playerResults): void
    {
        $stmt = $this->db->prepare(
            'UPDATE matches
             SET status = "completed",
                 winner_user_id = :winner_user_id,
                 completed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $matchId, ':winner_user_id' => $winnerUserId]);

        $update = $this->db->prepare(
            'UPDATE match_players
             SET hp_remaining = :hp_remaining,
                 result = :result
             WHERE match_id = :match_id AND slot_order = :slot_order'
        );
        foreach ($playerResults as $result) {
            $update->execute([
                ':match_id' => $matchId,
                ':slot_order' => $result['slot'],
                ':hp_remaining' => $result['hp_remaining'],
                ':result' => $result['result'],
            ]);
        }
    }

    public function history(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.mode, m.status, m.seed, m.simulator_version, m.created_at, m.completed_at, m.winner_user_id,
                    mp.result AS my_result,
                    opp.user_id AS opponent_id,
                    u.username AS opponent_username,
                    u.display_name AS opponent_display_name
             FROM matches m
             INNER JOIN match_players mp ON mp.match_id = m.id AND mp.user_id = :uid
             LEFT JOIN match_players opp ON opp.match_id = m.id AND opp.user_id <> :uid
             LEFT JOIN users u ON u.id = opp.user_id
             ORDER BY m.created_at DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function replay(int $matchId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.mode, m.status, m.seed, m.simulator_version, m.winner_user_id, m.created_at, m.completed_at
             FROM matches m
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $matchId]);
        $match = $stmt->fetch();
        if (!$match) {
            return [];
        }

        $playersStmt = $this->db->prepare(
            'SELECT mp.user_id, mp.slot_order, mp.result, mp.hp_remaining, u.username, u.display_name
             FROM match_players mp
             INNER JOIN users u ON u.id = mp.user_id
             WHERE mp.match_id = :match_id
             ORDER BY mp.slot_order ASC'
        );
        $playersStmt->execute([':match_id' => $matchId]);
        $players = $playersStmt->fetchAll();

        $eventsStmt = $this->db->prepare('SELECT id, tick, event_type, payload_json FROM match_events WHERE match_id = :match_id ORDER BY tick ASC, id ASC');
        $eventsStmt->execute([':match_id' => $matchId]);
        $events = array_map(function (array $row) {
            $row['payload'] = json_decode($row['payload_json'], true);
            unset($row['payload_json']);
            return $row;
        }, $eventsStmt->fetchAll());

        return [
            'match' => $match,
            'players' => $players,
            'events' => $events,
        ];
    }

    public function challenge(int $challengerId, int $targetId, int $seed, string $simVersion): int
    {
        $matchId = $this->createMatch($challengerId, 'challenge', $seed, $simVersion);
        $this->addPlayer($matchId, $challengerId, 0);
        $this->addPlayer($matchId, $targetId, 1);
        $this->setAwaitingSubmission($matchId);
        return $matchId;
    }

    public function messages(int $matchId, int $afterId = 0, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT mm.id, mm.match_id, mm.user_id, mm.message, mm.created_at, u.username, u.display_name
             FROM match_messages mm
             INNER JOIN users u ON u.id = mm.user_id
             WHERE mm.match_id = :match_id AND mm.id > :after_id
             ORDER BY mm.id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':match_id' => $matchId, ':after_id' => $afterId]);
        return $stmt->fetchAll();
    }

    public function addMessage(int $matchId, int $userId, string $message): int
    {
        $stmt = $this->db->prepare('INSERT INTO match_messages (match_id, user_id, message) VALUES (:match_id, :user_id, :message)');
        $stmt->execute([':match_id' => $matchId, ':user_id' => $userId, ':message' => $message]);
        return (int) $this->db->lastInsertId();
    }
}
