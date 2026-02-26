<?php

namespace BattleVue;

class Config
{
    private static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        $config = self::all();
        return $config[$key] ?? $default;
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $configPath = __DIR__ . '/../config/config.php';
        if (!file_exists($configPath)) {
            $examplePath = __DIR__ . '/../config/config.example.php';
            if (!file_exists($examplePath)) {
                throw new \RuntimeException('Missing config files.');
            }
            self::$cache = require $examplePath;
            return self::$cache;
        }

        $loaded = require $configPath;
        if (!is_array($loaded)) {
            throw new \RuntimeException('Invalid config.php format.');
        }

        self::$cache = $loaded;
        return self::$cache;
    }
}
