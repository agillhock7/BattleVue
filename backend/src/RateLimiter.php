<?php

namespace BattleVue;

class RateLimiter
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?: sys_get_temp_dir() . '/battlevue_rate_limits';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
    }

    public function hit(string $bucket, string $key, int $windowSeconds, int $max): bool
    {
        $safe = sha1($bucket . ':' . $key);
        $path = $this->dir . '/' . $safe . '.json';
        $now = time();

        $fp = fopen($path, 'c+');
        if (!$fp) {
            return true;
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $state = $raw ? json_decode($raw, true) : null;
        if (!is_array($state) || ($state['window_start'] ?? 0) + $windowSeconds <= $now) {
            $state = ['window_start' => $now, 'count' => 0];
        }

        $state['count']++;
        $allowed = $state['count'] <= $max;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $allowed;
    }
}
