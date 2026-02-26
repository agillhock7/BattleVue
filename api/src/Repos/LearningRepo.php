<?php

namespace BattleVue\Repos;

use PDO;

class LearningRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function listTopics(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, slug, title, description, is_custom, created_by_user_id
             FROM learning_topics
             WHERE is_active = 1
               AND (is_custom = 0 OR created_by_user_id = :user_id)
             ORDER BY is_custom ASC, title ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findTopicByIdForUser(int $topicId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM learning_topics
             WHERE id = :id
               AND is_active = 1
               AND (is_custom = 0 OR created_by_user_id = :user_id)
             LIMIT 1'
        );
        $stmt->execute([':id' => $topicId, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCustomTopic(int $userId, string $title, string $description, string $systemPrompt): int
    {
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?: 'custom-topic';
        $slugBase = trim($slugBase, '-');
        if ($slugBase === '') {
            $slugBase = 'custom-topic';
        }

        $slug = substr($slugBase, 0, 72) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $stmt = $this->db->prepare(
            'INSERT INTO learning_topics (slug, title, description, system_prompt, is_custom, created_by_user_id, is_active)
             VALUES (:slug, :title, :description, :system_prompt, 1, :created_by_user_id, 1)'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':description' => $description,
            ':system_prompt' => $systemPrompt,
            ':created_by_user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createSession(int $userId, int $topicId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO learning_sessions (user_id, topic_id, status, last_activity_at)
             VALUES (:user_id, :topic_id, "active", NOW())'
        );
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);
        return (int) $this->db->lastInsertId();
    }

    public function getSession(int $sessionId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ls.*, lt.slug AS topic_slug, lt.title AS topic_title, lt.description AS topic_description, lt.system_prompt
             FROM learning_sessions ls
             INNER JOIN learning_topics lt ON lt.id = ls.topic_id
             WHERE ls.id = :session_id AND ls.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':session_id' => $sessionId, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addMessage(
        int $sessionId,
        string $role,
        string $content,
        int $tokenCount,
        int $modelTokenCount,
        int $totalTokenCount
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO learning_messages (session_id, role, content, token_count, model_token_count, total_token_count)
             VALUES (:session_id, :role, :content, :token_count, :model_token_count, :total_token_count)'
        );
        $stmt->execute([
            ':session_id' => $sessionId,
            ':role' => $role,
            ':content' => $content,
            ':token_count' => $tokenCount,
            ':model_token_count' => $modelTokenCount,
            ':total_token_count' => $totalTokenCount,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listMessages(int $sessionId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, role, content, token_count, model_token_count, total_token_count, created_at
             FROM learning_messages
             WHERE session_id = :session_id
             ORDER BY id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':session_id' => $sessionId]);
        return $stmt->fetchAll();
    }

    public function listRecentMessagesForPrompt(int $sessionId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT role, content
             FROM learning_messages
             WHERE session_id = :session_id
             ORDER BY id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':session_id' => $sessionId]);
        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }

    public function addSessionTokens(int $sessionId, int $userTokens, int $modelTokens, int $totalTokens): void
    {
        $stmt = $this->db->prepare(
            'UPDATE learning_sessions
             SET cumulative_user_tokens = cumulative_user_tokens + :user_tokens,
                 cumulative_model_tokens = cumulative_model_tokens + :model_tokens,
                 cumulative_total_tokens = cumulative_total_tokens + :total_tokens,
                 last_activity_at = NOW(),
                 updated_at = NOW()
             WHERE id = :session_id'
        );
        $stmt->execute([
            ':session_id' => $sessionId,
            ':user_tokens' => $userTokens,
            ':model_tokens' => $modelTokens,
            ':total_tokens' => $totalTokens,
        ]);
    }

    public function getPendingCheckpoint(int $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM learning_checkpoints
             WHERE session_id = :session_id
               AND submitted_at IS NULL
             ORDER BY tier DESC
             LIMIT 1'
        );
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCheckpoint(int $sessionId, int $tier, array $quiz): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO learning_checkpoints (session_id, tier, quiz_json)
             VALUES (:session_id, :tier, :quiz_json)
             ON DUPLICATE KEY UPDATE quiz_json = VALUES(quiz_json), submitted_at = NULL, submitted_answers_json = NULL, score_percent = NULL, passed = NULL, awarded_points = 0'
        );
        $stmt->execute([
            ':session_id' => $sessionId,
            ':tier' => $tier,
            ':quiz_json' => json_encode($quiz, JSON_UNESCAPED_SLASHES),
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare('SELECT id FROM learning_checkpoints WHERE session_id = :session_id AND tier = :tier LIMIT 1');
        $lookup->execute([':session_id' => $sessionId, ':tier' => $tier]);
        $row = $lookup->fetch();
        return (int) ($row['id'] ?? 0);
    }

    public function submitCheckpoint(int $checkpointId, array $answers, int $scorePercent, bool $passed, int $awardedPoints): void
    {
        $stmt = $this->db->prepare(
            'UPDATE learning_checkpoints
             SET submitted_answers_json = :submitted_answers_json,
                 score_percent = :score_percent,
                 passed = :passed,
                 awarded_points = :awarded_points,
                 submitted_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $checkpointId,
            ':submitted_answers_json' => json_encode($answers, JSON_UNESCAPED_SLASHES),
            ':score_percent' => $scorePercent,
            ':passed' => $passed ? 1 : 0,
            ':awarded_points' => $awardedPoints,
        ]);
    }

    public function setLastCheckpointTier(int $sessionId, int $tier): void
    {
        $stmt = $this->db->prepare(
            'UPDATE learning_sessions
             SET last_checkpoint_tier = GREATEST(last_checkpoint_tier, :tier),
                 updated_at = NOW()
             WHERE id = :session_id'
        );
        $stmt->execute([':session_id' => $sessionId, ':tier' => $tier]);
    }

    public function listCheckpoints(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, tier, quiz_json, submitted_answers_json, score_percent, passed, awarded_points, created_at, submitted_at
             FROM learning_checkpoints
             WHERE session_id = :session_id
             ORDER BY tier ASC'
        );
        $stmt->execute([':session_id' => $sessionId]);

        return array_map(static function (array $row) {
            $row['quiz'] = json_decode($row['quiz_json'], true);
            $row['submitted_answers'] = $row['submitted_answers_json'] ? json_decode($row['submitted_answers_json'], true) : null;
            unset($row['quiz_json'], $row['submitted_answers_json']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function addRewardPoints(int $userId, int $points, string $source, array $metadata = []): void
    {
        $stmt = $this->db->prepare('UPDATE users SET bot_points = bot_points + :points WHERE id = :id');
        $stmt->execute([':id' => $userId, ':points' => $points]);

        $ledger = $this->db->prepare(
            'INSERT INTO reward_ledger (user_id, source, points, metadata_json)
             VALUES (:user_id, :source, :points, :metadata_json)'
        );
        $ledger->execute([
            ':user_id' => $userId,
            ':source' => $source,
            ':points' => $points,
            ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getBotPoints(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT bot_points FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return (int) ($row['bot_points'] ?? 0);
    }
}
