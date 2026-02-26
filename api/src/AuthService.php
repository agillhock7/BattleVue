<?php

namespace BattleVue;

use PDO;

class AuthService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function register(string $username, string $email, string $password): int
    {
        $pepper = (string) Config::get('PASSWORD_PEPPER', '');
        $passwordHash = password_hash($password . $pepper, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash, display_name) VALUES (:username, :email, :password_hash, :display_name)');
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':display_name' => $username,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function login(string $identity, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :identity OR email = :identity LIMIT 1');
        $stmt->execute([':identity' => $identity]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        $pepper = (string) Config::get('PASSWORD_PEPPER', '');
        if (!password_verify($password . $pepper, $user['password_hash'])) {
            return null;
        }

        $this->revokeAll((int) $user['id']);
        $this->issueSession((int) $user['id']);

        return $this->publicUser($user);
    }

    public function logout(): void
    {
        $cookieName = (string) Config::get('SESSION_COOKIE_NAME', 'battlevue_session');
        $token = $_COOKIE[$cookieName] ?? '';
        if ($token !== '') {
            $hash = hash('sha256', $token);
            $stmt = $this->db->prepare('UPDATE sessions SET revoked_at = NOW() WHERE token_hash = :token_hash');
            $stmt->execute([':token_hash' => $hash]);
        }

        $this->clearCookies();
    }

    public function currentUser(): ?array
    {
        $cookieName = (string) Config::get('SESSION_COOKIE_NAME', 'battlevue_session');
        $token = $_COOKIE[$cookieName] ?? '';
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT s.id as session_id, s.csrf_token, u.*
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :token_hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->clearCookies();
            return null;
        }

        $touch = $this->db->prepare('UPDATE sessions SET last_seen_at = NOW() WHERE id = :id');
        $touch->execute([':id' => $row['session_id']]);

        return [
            'user' => $this->publicUser($row),
            'session_id' => (int) $row['session_id'],
            'csrf_token' => $row['csrf_token'],
        ];
    }

    public function requireUser(): array
    {
        $ctx = $this->currentUser();
        if (!$ctx) {
            Response::error('Unauthorized', 401);
        }
        return $ctx;
    }

    private function issueSession(int $userId): void
    {
        $cookieName = (string) Config::get('SESSION_COOKIE_NAME', 'battlevue_session');
        $ttl = (int) Config::get('SESSION_TTL_SECONDS', 1209600);
        $token = bin2hex(random_bytes(32));
        $csrfToken = Csrf::token();
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+' . $ttl . ' seconds'))->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO sessions (user_id, token_hash, csrf_token, ip_address, user_agent, expires_at)
             VALUES (:user_id, :token_hash, :csrf_token, :ip_address, :user_agent, :expires_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':csrf_token' => $csrfToken,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':expires_at' => $expiresAt,
        ]);

        $isSecure = strtolower((string) Config::get('APP_ENV', 'dev')) === 'prod';
        setcookie($cookieName, $token, [
            'expires' => time() + $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        setcookie('battlevue_csrf', $csrfToken, [
            'expires' => time() + $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private function revokeAll(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE sessions SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL');
        $stmt->execute([':user_id' => $userId]);
    }

    private function clearCookies(): void
    {
        $cookieName = (string) Config::get('SESSION_COOKIE_NAME', 'battlevue_session');
        $isSecure = strtolower((string) Config::get('APP_ENV', 'dev')) === 'prod';
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('battlevue_csrf', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private function publicUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'display_name' => $row['display_name'],
            'avatar_url' => $row['avatar_url'],
            'created_at' => $row['created_at'],
        ];
    }
}
