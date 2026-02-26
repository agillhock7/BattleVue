<?php

namespace BattleVue\Validators;

class RulesetValidator
{
    private const MAX_RULES = 30;
    private const MAX_CONDITIONS = 6;
    private const MAX_NESTED = 2;

    private const SENSORS = [
        'self_hp_pct',
        'enemy_hp_pct',
        'self_lane',
        'enemy_lane',
        'tick',
        'cooldown_ready',
    ];

    private const OPERATORS = ['==', '!=', '>', '>=', '<', '<=', 'in'];

    private const ACTIONS = [
        'attack_lane',
        'guard',
        'shift_lane',
        'wait',
    ];

    private const LANES = ['left', 'mid', 'right'];

    public function validate(array $payload): array
    {
        $errors = [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = 'Ruleset name must be 1-128 characters.';
        }

        $rules = $payload['rules'] ?? [];
        if (!is_array($rules)) {
            return ['Rules must be an array.'];
        }
        if (count($rules) > self::MAX_RULES) {
            $errors[] = 'Rules exceed max count of 30.';
        }

        foreach ($rules as $idx => $rule) {
            if (!is_array($rule)) {
                $errors[] = "Rule #{$idx} must be an object.";
                continue;
            }

            $priority = (int) ($rule['priority'] ?? 0);
            if ($priority < 0 || $priority > 1000) {
                $errors[] = "Rule #{$idx} has invalid priority.";
            }

            $when = $rule['when'] ?? [];
            if (!$this->validateWhen($when, 0, $conditionCount = 0)) {
                $errors[] = "Rule #{$idx} has invalid conditions.";
            }
            if ($conditionCount > self::MAX_CONDITIONS) {
                $errors[] = "Rule #{$idx} exceeds max conditions (6).";
            }

            $then = $rule['then'] ?? [];
            if (!is_array($then)) {
                $errors[] = "Rule #{$idx} action is invalid.";
            } else {
                $action = (string) ($then['action'] ?? '');
                if (!in_array($action, self::ACTIONS, true)) {
                    $errors[] = "Rule #{$idx} action '{$action}' is not allowlisted.";
                }
                if ($action === 'attack_lane') {
                    $lane = (string) ($then['params']['lane'] ?? '');
                    if (!in_array($lane, self::LANES, true)) {
                        $errors[] = "Rule #{$idx} attack_lane requires lane in left/mid/right.";
                    }
                }
                if ($action === 'shift_lane') {
                    $lane = (string) ($then['params']['lane'] ?? '');
                    if (!in_array($lane, self::LANES, true)) {
                        $errors[] = "Rule #{$idx} shift_lane requires lane in left/mid/right.";
                    }
                }
            }
        }

        return $errors;
    }

    private function validateWhen($node, int $depth, int &$conditionCount): bool
    {
        if ($depth > self::MAX_NESTED) {
            return false;
        }

        if (!is_array($node)) {
            return false;
        }

        if (isset($node['all']) || isset($node['any'])) {
            $key = isset($node['all']) ? 'all' : 'any';
            $children = $node[$key];
            if (!is_array($children)) {
                return false;
            }
            foreach ($children as $child) {
                if (!$this->validateWhen($child, $depth + 1, $conditionCount)) {
                    return false;
                }
            }
            return true;
        }

        $sensor = (string) ($node['sensor'] ?? '');
        $op = (string) ($node['op'] ?? '');
        if (!in_array($sensor, self::SENSORS, true) || !in_array($op, self::OPERATORS, true)) {
            return false;
        }

        $conditionCount++;
        return true;
    }
}
