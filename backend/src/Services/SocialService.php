<?php

namespace BattleVue\Services;

use BattleVue\Repos\NotificationRepo;
use BattleVue\Repos\SocialRepo;
use BattleVue\Repos\UserRepo;
use RuntimeException;

class SocialService
{
    public function __construct(
        private UserRepo $userRepo,
        private SocialRepo $socialRepo,
        private NotificationRepo $notificationRepo
    ) {
    }

    public function searchUsers(int $userId, string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }
        return $this->userRepo->search($query, $userId);
    }

    public function sendFriendRequest(int $senderId, int $receiverId): int
    {
        if ($senderId === $receiverId) {
            throw new RuntimeException('Cannot friend yourself.');
        }
        if (!$this->userRepo->findById($receiverId)) {
            throw new RuntimeException('User not found.');
        }
        if ($this->socialRepo->hasBlockBetween($senderId, $receiverId)) {
            throw new RuntimeException('Cannot send request due to block.');
        }
        if ($this->socialRepo->areFriends($senderId, $receiverId)) {
            throw new RuntimeException('Users are already friends.');
        }
        if ($this->socialRepo->hasPendingRequestBetween($senderId, $receiverId)) {
            throw new RuntimeException('A pending friend request already exists.');
        }

        $requestId = $this->socialRepo->createFriendRequest($senderId, $receiverId);
        $this->notificationRepo->create($receiverId, 'friend_request', 'New friend request', 'You received a friend request.', [
            'request_id' => $requestId,
            'sender_user_id' => $senderId,
        ]);

        return $requestId;
    }

    public function respondFriendRequest(int $userId, int $requestId, string $action): void
    {
        $row = $this->socialRepo->getRequestForReceiver($requestId, $userId);
        if (!$row || $row['status'] !== 'pending') {
            throw new RuntimeException('Friend request not found.');
        }

        if (!in_array($action, ['accepted', 'declined'], true)) {
            throw new RuntimeException('Invalid action.');
        }

        $this->socialRepo->updateRequestStatus($requestId, $action);
        if ($action === 'accepted') {
            $this->socialRepo->createFriendship((int) $row['sender_user_id'], (int) $row['receiver_user_id']);
            $this->notificationRepo->create((int) $row['sender_user_id'], 'friend_request_accepted', 'Friend request accepted', 'Your friend request was accepted.', [
                'request_id' => $requestId,
                'receiver_user_id' => $userId,
            ]);
        }
    }

    public function listFriends(int $userId): array
    {
        return [
            'friends' => $this->socialRepo->listFriends($userId),
            'requests' => $this->socialRepo->listFriendRequests($userId),
        ];
    }

    public function removeFriend(int $userId, int $friendId): void
    {
        $this->socialRepo->removeFriendship($userId, $friendId);
    }

    public function addBlock(int $userId, int $blockedId): void
    {
        if ($userId === $blockedId) {
            throw new RuntimeException('Cannot block yourself.');
        }
        $this->socialRepo->addBlock($userId, $blockedId);
        $this->socialRepo->removeFriendship($userId, $blockedId);
    }
}
