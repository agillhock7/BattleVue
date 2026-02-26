<?php

namespace BattleVue\Repos;

use PDO;

class SocialRepo
{
    public function __construct(private PDO $db)
    {
    }

    public function hasBlockBetween(int $a, int $b): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM blocks
             WHERE (blocker_user_id = :a AND blocked_user_id = :b)
                OR (blocker_user_id = :b AND blocked_user_id = :a)
             LIMIT 1'
        );
        $stmt->execute([':a' => $a, ':b' => $b]);
        return (bool) $stmt->fetch();
    }

    public function createFriendRequest(int $senderId, int $receiverId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO friend_requests (sender_user_id, receiver_user_id, status)
             VALUES (:sender, :receiver, "pending")'
        );
        $stmt->execute([':sender' => $senderId, ':receiver' => $receiverId]);
        return (int) $this->db->lastInsertId();
    }

    public function hasPendingRequestBetween(int $a, int $b): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM friend_requests
             WHERE ((sender_user_id = :a AND receiver_user_id = :b)
                OR (sender_user_id = :b AND receiver_user_id = :a))
               AND status = "pending"
             LIMIT 1'
        );
        $stmt->execute([':a' => $a, ':b' => $b]);
        return (bool) $stmt->fetch();
    }

    public function areFriends(int $a, int $b): bool
    {
        $u1 = min($a, $b);
        $u2 = max($a, $b);
        $stmt = $this->db->prepare(
            'SELECT id
             FROM friends
             WHERE user_one_id = :u1
               AND user_two_id = :u2
             LIMIT 1'
        );
        $stmt->execute([':u1' => $u1, ':u2' => $u2]);
        return (bool) $stmt->fetch();
    }

    public function getRequestForReceiver(int $requestId, int $receiverId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM friend_requests WHERE id = :id AND receiver_user_id = :receiver LIMIT 1');
        $stmt->execute([':id' => $requestId, ':receiver' => $receiverId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateRequestStatus(int $requestId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE friend_requests SET status = :status, responded_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $requestId]);
    }

    public function createFriendship(int $a, int $b): void
    {
        $u1 = min($a, $b);
        $u2 = max($a, $b);
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO friends (user_one_id, user_two_id)
             VALUES (:u1, :u2)'
        );
        $stmt->execute([':u1' => $u1, ':u2' => $u2]);
    }

    public function listFriends(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_url, f.created_at AS friended_at
             FROM friends f
             INNER JOIN users u ON u.id = IF(f.user_one_id = :uid, f.user_two_id, f.user_one_id)
             WHERE f.user_one_id = :uid OR f.user_two_id = :uid
             ORDER BY u.username ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function listFriendRequests(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT fr.id, fr.sender_user_id, fr.receiver_user_id, fr.status, fr.created_at,
                    su.username AS sender_username, su.display_name AS sender_display_name,
                    ru.username AS receiver_username, ru.display_name AS receiver_display_name
             FROM friend_requests fr
             INNER JOIN users su ON su.id = fr.sender_user_id
             INNER JOIN users ru ON ru.id = fr.receiver_user_id
             WHERE fr.receiver_user_id = :uid AND fr.status = "pending"
             ORDER BY fr.created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function removeFriendship(int $a, int $b): void
    {
        $u1 = min($a, $b);
        $u2 = max($a, $b);
        $stmt = $this->db->prepare('DELETE FROM friends WHERE user_one_id = :u1 AND user_two_id = :u2');
        $stmt->execute([':u1' => $u1, ':u2' => $u2]);
    }

    public function addBlock(int $blockerId, int $blockedId): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO blocks (blocker_user_id, blocked_user_id) VALUES (:blocker, :blocked)');
        $stmt->execute([':blocker' => $blockerId, ':blocked' => $blockedId]);
    }
}
