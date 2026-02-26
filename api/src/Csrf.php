<?php

namespace BattleVue;

class Csrf
{
    public static function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function verify(string $sessionToken): bool
    {
        $cookieToken = $_COOKIE['battlevue_csrf'] ?? '';
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if ($headerToken === '' && isset($_POST['_csrf'])) {
            $headerToken = (string) $_POST['_csrf'];
        }

        if ($cookieToken === '' || $headerToken === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $cookieToken) && hash_equals($sessionToken, $headerToken);
    }
}
