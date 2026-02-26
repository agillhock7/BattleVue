<?php

namespace BattleVue\Services;

use BattleVue\Config;
use BattleVue\Repos\MatchRepo;
use BattleVue\Repos\NotificationRepo;
use BattleVue\Repos\SocialRepo;
use BattleVue\Simulator\SimulatorV1;
use RuntimeException;

class MatchService
{
    public function __construct(
        private MatchRepo $matchRepo,
        private BotService $botService,
        private SocialRepo $socialRepo,
        private NotificationRepo $notificationRepo,
        private SimulatorV1 $simulator
    ) {
    }

    public function queue(int $userId, string $mode): array
    {
        if (!in_array($mode, ['casual', 'ranked'], true)) {
            throw new RuntimeException('Invalid queue mode.');
        }
        if ($this->matchRepo->userHasActiveMatch($userId)) {
            throw new RuntimeException('You already have an active match.');
        }

        $open = $this->matchRepo->findOpenQueueMatch($userId, $mode);
        if (!$open) {
            $matchId = $this->matchRepo->createMatch($userId, $mode, random_int(1, PHP_INT_MAX), (string) Config::get('SIMULATOR_VERSION', 'v1'));
            $this->matchRepo->addPlayer($matchId, $userId, 0);
            return ['match_id' => $matchId, 'status' => 'queued'];
        }

        $matchId = (int) $open['id'];
        $this->matchRepo->addPlayer($matchId, $userId, 1);
        $this->matchRepo->setAwaitingSubmission($matchId);

        $players = $this->matchRepo->getPlayers($matchId);
        foreach ($players as $player) {
            if ((int) $player['user_id'] !== $userId) {
                $this->notificationRepo->create((int) $player['user_id'], 'match_found', 'Match found', 'Your queue match is ready.', ['match_id' => $matchId]);
            }
        }

        return ['match_id' => $matchId, 'status' => 'awaiting_submission'];
    }

    public function challenge(int $userId, int $targetUserId): array
    {
        if ($userId === $targetUserId) {
            throw new RuntimeException('Cannot challenge yourself.');
        }
        if ($this->matchRepo->userHasActiveMatch($userId) || $this->matchRepo->userHasActiveMatch($targetUserId)) {
            throw new RuntimeException('One of the users already has an active match.');
        }
        if ($this->socialRepo->hasBlockBetween($userId, $targetUserId)) {
            throw new RuntimeException('Challenge blocked by user relationship.');
        }

        $friends = $this->socialRepo->listFriends($userId);
        $friendIds = array_map(static fn($f) => (int) $f['id'], $friends);
        if (!in_array($targetUserId, $friendIds, true)) {
            throw new RuntimeException('You can only challenge friends.');
        }

        $matchId = $this->matchRepo->challenge($userId, $targetUserId, random_int(1, PHP_INT_MAX), (string) Config::get('SIMULATOR_VERSION', 'v1'));
        $this->notificationRepo->create($targetUserId, 'match_challenge', 'New challenge', 'A friend challenged you.', ['match_id' => $matchId]);
        return ['match_id' => $matchId, 'status' => 'awaiting_submission'];
    }

    public function submitLoadout(int $userId, int $matchId, int $blueprintId, int $rulesetId): array
    {
        $match = $this->matchRepo->getMatchForUser($matchId, $userId);
        if (!$match) {
            throw new RuntimeException('Match not found.');
        }
        if (!in_array($match['status'], ['awaiting_submission', 'queued'], true)) {
            throw new RuntimeException('Match is not accepting submissions.');
        }

        $blueprint = $this->botService->getBlueprintForUser($userId, $blueprintId);
        $ruleset = $this->botService->getRulesetForUser($userId, $rulesetId);
        $this->matchRepo->submitPlayerLoadout($matchId, $userId, $blueprintId, $rulesetId, $blueprint, $ruleset);

        $simulated = false;
        if ($this->matchRepo->areAllSubmitted($matchId)) {
            $this->simulate($matchId, true);
            $simulated = true;
        }

        return ['submitted' => true, 'simulated' => $simulated];
    }

    public function simulate(int $matchId, bool $internalAuto = false): array
    {
        $players = $this->matchRepo->getPlayers($matchId);
        if (count($players) !== 2) {
            throw new RuntimeException('Simulation requires 2 players.');
        }

        foreach ($players as $player) {
            if (empty($player['submitted_at'])) {
                throw new RuntimeException('All players must submit loadouts first.');
            }
        }

        $match = $this->matchRepo->getMatchById($matchId);
        if (!$match) {
            throw new RuntimeException('Match not found.');
        }
        if (!in_array($match['status'], ['awaiting_submission', 'simulating'], true)) {
            throw new RuntimeException('Match is not in a simulatable state.');
        }

        $this->matchRepo->markSimulating($matchId);

        $seed = (int) $match['seed'];
        $result = $this->simulator->run($seed, $players[0], $players[1]);

        $winnerUserId = null;
        if ($result['winner_slot'] !== null) {
            foreach ($players as $player) {
                if ((int) $player['slot_order'] === (int) $result['winner_slot']) {
                    $winnerUserId = (int) $player['user_id'];
                    break;
                }
            }
        }

        $this->matchRepo->writeEvents($matchId, $result['events']);
        $this->matchRepo->finalizeMatch($matchId, $winnerUserId, $result['players']);

        foreach ($players as $player) {
            $this->notificationRepo->create((int) $player['user_id'], 'match_complete', 'Match complete', 'Your match simulation has finished.', [
                'match_id' => $matchId,
                'winner_user_id' => $winnerUserId,
                'auto' => $internalAuto,
            ]);
        }

        return ['match_id' => $matchId, 'winner_user_id' => $winnerUserId];
    }

    public function history(int $userId): array
    {
        return $this->matchRepo->history($userId);
    }

    public function detail(int $userId, int $matchId): array
    {
        $detail = $this->matchRepo->matchDetailForUser($matchId, $userId);
        if (!$detail) {
            throw new RuntimeException('Match not found.');
        }

        $players = $this->matchRepo->playersForMatch($matchId);
        $self = null;
        foreach ($players as $player) {
            if ((int) $player['user_id'] === $userId) {
                $self = $player;
                break;
            }
        }

        if (!$self) {
            throw new RuntimeException('Match not found.');
        }

        $status = (string) ($detail['status'] ?? 'queued');
        $canSubmit = in_array($status, ['queued', 'awaiting_submission'], true) && empty($self['submitted_at']);
        $canReplay = $status === 'completed';

        return [
            'match' => [
                'id' => (int) $detail['id'],
                'mode' => $detail['mode'],
                'status' => $status,
                'seed' => (int) $detail['seed'],
                'simulator_version' => $detail['simulator_version'],
                'winner_user_id' => isset($detail['winner_user_id']) ? (int) $detail['winner_user_id'] : null,
                'created_at' => $detail['created_at'],
                'started_at' => $detail['started_at'],
                'completed_at' => $detail['completed_at'],
            ],
            'self' => [
                'user_id' => (int) $self['user_id'],
                'slot_order' => (int) $self['slot_order'],
                'blueprint_id' => isset($self['blueprint_id']) ? (int) $self['blueprint_id'] : null,
                'ruleset_id' => isset($self['ruleset_id']) ? (int) $self['ruleset_id'] : null,
                'submitted_at' => $self['submitted_at'],
                'result' => $self['result'],
                'hp_remaining' => isset($self['hp_remaining']) ? (int) $self['hp_remaining'] : null,
            ],
            'players' => array_map(static function (array $row) {
                return [
                    'user_id' => (int) $row['user_id'],
                    'slot_order' => (int) $row['slot_order'],
                    'username' => $row['username'],
                    'display_name' => $row['display_name'],
                    'submitted_at' => $row['submitted_at'],
                    'result' => $row['result'],
                    'hp_remaining' => isset($row['hp_remaining']) ? (int) $row['hp_remaining'] : null,
                ];
            }, $players),
            'can_submit' => $canSubmit,
            'can_replay' => $canReplay,
        ];
    }

    public function replay(int $userId, int $matchId): array
    {
        $access = $this->matchRepo->getMatchForUser($matchId, $userId);
        if (!$access) {
            throw new RuntimeException('Match not found.');
        }
        return $this->matchRepo->replay($matchId);
    }

    public function messages(int $userId, int $matchId, int $afterId): array
    {
        $access = $this->matchRepo->getMatchForUser($matchId, $userId);
        if (!$access) {
            throw new RuntimeException('Match not found.');
        }
        return $this->matchRepo->messages($matchId, $afterId);
    }

    public function addMessage(int $userId, int $matchId, string $message): array
    {
        $access = $this->matchRepo->getMatchForUser($matchId, $userId);
        if (!$access) {
            throw new RuntimeException('Match not found.');
        }

        $trimmed = trim($message);
        if ($trimmed === '' || mb_strlen($trimmed) > 280) {
            throw new RuntimeException('Message must be 1-280 chars.');
        }

        $id = $this->matchRepo->addMessage($matchId, $userId, $trimmed);
        return ['message_id' => $id];
    }
}
