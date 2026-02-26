<?php

namespace BattleVue\Validators;

class BlueprintValidator
{
    private const ALLOWED_LANES = ['left', 'mid', 'right', 'adaptive'];
    private const ALLOWED_MODULE_TYPES = ['weapon', 'defense', 'mobility', 'utility'];

    public function validate(array $payload): array
    {
        $errors = [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = 'Name must be 1-128 characters.';
        }

        $chassis = (string) ($payload['chassis'] ?? '');
        if (!preg_match('/^[a-z0-9\-]{3,64}$/', $chassis)) {
            $errors[] = 'Chassis must be a valid slug.';
        }

        $lanePref = (string) ($payload['lane_pref'] ?? 'adaptive');
        if (!in_array($lanePref, self::ALLOWED_LANES, true)) {
            $errors[] = 'Invalid lane preference.';
        }

        $modules = $payload['modules'] ?? [];
        if (!is_array($modules) || count($modules) > 12) {
            $errors[] = 'Modules must be an array of at most 12 entries.';
        } else {
            foreach ($modules as $idx => $module) {
                if (!is_array($module)) {
                    $errors[] = "Module #{$idx} must be an object.";
                    continue;
                }
                $type = (string) ($module['type'] ?? '');
                if (!in_array($type, self::ALLOWED_MODULE_TYPES, true)) {
                    $errors[] = "Module #{$idx} has invalid type.";
                }
                $slug = (string) ($module['slug'] ?? '');
                if (!preg_match('/^[a-z0-9\-]{3,64}$/', $slug)) {
                    $errors[] = "Module #{$idx} has invalid slug.";
                }
            }
        }

        $stats = $payload['stats'] ?? [];
        if (!is_array($stats)) {
            $errors[] = 'Stats must be an object.';
        } else {
            foreach (['hp', 'speed', 'power'] as $key) {
                $value = (int) ($stats[$key] ?? 0);
                if ($value < 0 || $value > 999) {
                    $errors[] = "Stat {$key} must be between 0 and 999.";
                }
            }
        }

        return $errors;
    }
}
