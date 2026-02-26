<?php

namespace BattleVue\Simulator;

class SimulatorV1
{
    private int $rngState;

    public function run(int $seed, array $playerA, array $playerB): array
    {
        $this->rngState = $seed > 0 ? $seed : 2463534242;

        $botA = $this->buildBotState($playerA, 0);
        $botB = $this->buildBotState($playerB, 1);

        $events = [];
        $maxTicks = 200;

        for ($tick = 1; $tick <= $maxTicks; $tick++) {
            $botA['cooldown'] = max(0, $botA['cooldown'] - 1);
            $botB['cooldown'] = max(0, $botB['cooldown'] - 1);
            $botA['guard'] = false;
            $botB['guard'] = false;

            $actionA = $this->decideAction($botA, $botB, $tick);
            $actionB = $this->decideAction($botB, $botA, $tick);

            $turnOrder = $this->resolveOrder($botA, $botB);

            foreach ($turnOrder as $actorIdx) {
                if ($botA['hp'] <= 0 || $botB['hp'] <= 0) {
                    break;
                }

                if ($actorIdx === 0) {
                    $this->applyAction($botA, $botB, $actionA, $tick, $events);
                } else {
                    $this->applyAction($botB, $botA, $actionB, $tick, $events);
                }
            }

            $events[] = [
                'tick' => $tick,
                'event_type' => 'state',
                'payload' => [
                    'a_hp' => $botA['hp'],
                    'b_hp' => $botB['hp'],
                    'a_lane' => $botA['lane'],
                    'b_lane' => $botB['lane'],
                ],
            ];

            if ($botA['hp'] <= 0 || $botB['hp'] <= 0) {
                break;
            }
        }

        $winnerSlot = null;
        $resultA = 'draw';
        $resultB = 'draw';
        if ($botA['hp'] > $botB['hp']) {
            $winnerSlot = 0;
            $resultA = 'win';
            $resultB = 'loss';
        } elseif ($botB['hp'] > $botA['hp']) {
            $winnerSlot = 1;
            $resultA = 'loss';
            $resultB = 'win';
        }

        $events[] = [
            'tick' => 200,
            'event_type' => 'match_end',
            'payload' => [
                'winner_slot' => $winnerSlot,
                'a_hp' => $botA['hp'],
                'b_hp' => $botB['hp'],
            ],
        ];

        return [
            'events' => $events,
            'winner_slot' => $winnerSlot,
            'players' => [
                ['slot' => 0, 'hp_remaining' => $botA['hp'], 'result' => $resultA],
                ['slot' => 1, 'hp_remaining' => $botB['hp'], 'result' => $resultB],
            ],
        ];
    }

    private function buildBotState(array $player, int $slot): array
    {
        $blueprint = $player['blueprint_snapshot'] ?? [];
        $ruleset = $player['ruleset_snapshot'] ?? [];
        $stats = $blueprint['stats'] ?? [];

        $lane = $blueprint['lane_pref'] ?? 'adaptive';
        if (!in_array($lane, ['left', 'mid', 'right'], true)) {
            $lane = 'mid';
        }

        return [
            'user_id' => (int) $player['user_id'],
            'slot' => $slot,
            'hp' => max(1, (int) ($stats['hp'] ?? 100)),
            'max_hp' => max(1, (int) ($stats['hp'] ?? 100)),
            'speed' => max(1, (int) ($stats['speed'] ?? 10)),
            'power' => max(1, (int) ($stats['power'] ?? 10)),
            'lane' => $lane,
            'cooldown' => 0,
            'guard' => false,
            'rules' => $ruleset['rules'] ?? [],
        ];
    }

    private function resolveOrder(array $a, array $b): array
    {
        if ($a['speed'] === $b['speed']) {
            return $this->randInt(0, 1) === 0 ? [0, 1] : [1, 0];
        }
        return $a['speed'] > $b['speed'] ? [0, 1] : [1, 0];
    }

    private function decideAction(array $self, array $enemy, int $tick): array
    {
        $default = ['action' => 'wait', 'params' => []];
        $rules = is_array($self['rules']) ? $self['rules'] : [];

        usort($rules, static function ($l, $r) {
            return ((int) ($l['priority'] ?? 0)) <=> ((int) ($r['priority'] ?? 0));
        });

        foreach ($rules as $rule) {
            $when = $rule['when'] ?? [];
            if (!$this->matchesWhen($when, $self, $enemy, $tick)) {
                continue;
            }
            $then = $rule['then'] ?? null;
            if (!is_array($then) || !isset($then['action'])) {
                continue;
            }
            return [
                'action' => (string) $then['action'],
                'params' => is_array($then['params'] ?? null) ? $then['params'] : [],
            ];
        }

        return $default;
    }

    private function matchesWhen($node, array $self, array $enemy, int $tick, int $depth = 0): bool
    {
        if (!is_array($node) || $depth > 2) {
            return false;
        }

        if (isset($node['all']) && is_array($node['all'])) {
            foreach ($node['all'] as $child) {
                if (!$this->matchesWhen($child, $self, $enemy, $tick, $depth + 1)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($node['any']) && is_array($node['any'])) {
            foreach ($node['any'] as $child) {
                if ($this->matchesWhen($child, $self, $enemy, $tick, $depth + 1)) {
                    return true;
                }
            }
            return false;
        }

        $sensor = (string) ($node['sensor'] ?? '');
        $op = (string) ($node['op'] ?? '==');
        $value = $node['value'] ?? null;

        $actual = $this->sensorValue($sensor, $self, $enemy, $tick);
        return $this->compare($actual, $op, $value);
    }

    private function sensorValue(string $sensor, array $self, array $enemy, int $tick)
    {
        switch ($sensor) {
            case 'self_hp_pct':
                return (int) round(($self['hp'] / max(1, $self['max_hp'])) * 100);
            case 'enemy_hp_pct':
                return (int) round(($enemy['hp'] / max(1, $enemy['max_hp'])) * 100);
            case 'self_lane':
                return $self['lane'];
            case 'enemy_lane':
                return $enemy['lane'];
            case 'tick':
                return $tick;
            case 'cooldown_ready':
                return $self['cooldown'] <= 0;
            default:
                return null;
        }
    }

    private function compare($actual, string $op, $value): bool
    {
        switch ($op) {
            case '==':
                return $actual == $value;
            case '!=':
                return $actual != $value;
            case '>':
                return $actual > $value;
            case '>=':
                return $actual >= $value;
            case '<':
                return $actual < $value;
            case '<=':
                return $actual <= $value;
            case 'in':
                return is_array($value) && in_array($actual, $value, true);
            default:
                return false;
        }
    }

    private function applyAction(array &$actor, array &$target, array $action, int $tick, array &$events): void
    {
        $type = $action['action'] ?? 'wait';
        $params = is_array($action['params'] ?? null) ? $action['params'] : [];

        if ($type === 'guard') {
            $actor['guard'] = true;
            $events[] = [
                'tick' => $tick,
                'event_type' => 'guard',
                'payload' => [
                    'slot' => $actor['slot'],
                ],
            ];
            return;
        }

        if ($type === 'shift_lane') {
            $lane = (string) ($params['lane'] ?? 'mid');
            if (!in_array($lane, ['left', 'mid', 'right'], true)) {
                $lane = 'mid';
            }
            $actor['lane'] = $lane;
            $events[] = [
                'tick' => $tick,
                'event_type' => 'shift_lane',
                'payload' => [
                    'slot' => $actor['slot'],
                    'lane' => $lane,
                ],
            ];
            return;
        }

        if ($type === 'attack_lane' && $actor['cooldown'] <= 0) {
            $lane = (string) ($params['lane'] ?? $actor['lane']);
            if (!in_array($lane, ['left', 'mid', 'right'], true)) {
                $lane = $actor['lane'];
            }

            $damage = $actor['power'] + $this->randInt(-2, 2);
            if ($target['lane'] !== $lane) {
                $damage = 0;
            }
            if ($target['guard']) {
                $damage = (int) floor($damage / 2);
            }
            $damage = max(0, $damage);
            $target['hp'] = max(0, $target['hp'] - $damage);
            $actor['cooldown'] = 2;

            $events[] = [
                'tick' => $tick,
                'event_type' => 'attack',
                'payload' => [
                    'slot' => $actor['slot'],
                    'target_slot' => $target['slot'],
                    'lane' => $lane,
                    'damage' => $damage,
                    'target_hp_after' => $target['hp'],
                ],
            ];
            return;
        }

        $events[] = [
            'tick' => $tick,
            'event_type' => 'wait',
            'payload' => [
                'slot' => $actor['slot'],
            ],
        ];
    }

    private function randInt(int $min, int $max): int
    {
        $next = $this->xorshift32();
        $range = $max - $min + 1;
        return $min + (int) ($next % $range);
    }

    private function xorshift32(): int
    {
        $x = $this->rngState;
        $x ^= ($x << 13);
        $x ^= ($x >> 17);
        $x ^= ($x << 5);
        $this->rngState = $x & 0xFFFFFFFF;
        return $this->rngState;
    }
}
