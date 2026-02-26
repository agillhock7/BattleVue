<?php

namespace BattleVue\Services;

use BattleVue\AuthService;
use BattleVue\Config;
use PDO;
use RuntimeException;

class OAuthService
{
    private const STATE_COOKIE = 'battlevue_oauth_state';

    public function __construct(private PDO $db, private AuthService $authService)
    {
    }

    public function authorizationUrl(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->providerConfig($provider);
        $state = $this->issueState($provider);

        if ($provider === 'discord') {
            return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $config['client_id'],
                'scope' => 'identify email',
                'redirect_uri' => $config['redirect_uri'],
                'state' => $state,
                'prompt' => 'consent',
            ]);
        }

        return 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => 'read:user user:email',
            'state' => $state,
        ]);
    }

    public function authenticateFromCallback(string $provider, string $code, string $state): array
    {
        $provider = $this->normalizeProvider($provider);
        if ($code === '' || $state === '') {
            throw new RuntimeException('Missing OAuth callback parameters.');
        }

        $this->verifyState($provider, $state);
        $profile = $provider === 'discord'
            ? $this->discordProfileFromCode($code)
            : $this->githubProfileFromCode($code);

        $userId = $this->resolveUser($provider, $profile);
        $user = $this->authService->loginByUserId($userId);
        if (!$user) {
            throw new RuntimeException('Failed to create session for OAuth user.');
        }

        return $user;
    }

    private function resolveUser(string $provider, array $profile): int
    {
        $providerUserId = (string) ($profile['provider_user_id'] ?? '');
        if ($providerUserId === '') {
            throw new RuntimeException('OAuth provider user id missing.');
        }

        $existing = $this->findLinkedUserId($provider, $providerUserId);
        if ($existing !== null) {
            $this->upsertAccount($existing, $provider, $providerUserId, $profile);
            return $existing;
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $userId = null;
        if ($email !== '') {
            $existingUser = $this->authService->findUserByEmail($email);
            if ($existingUser) {
                $userId = (int) $existingUser['id'];
            }
        }

        if ($userId === null) {
            $username = $this->uniqueUsername($provider, (string) ($profile['username'] ?? ''));
            $finalEmail = $email !== '' ? $email : $this->uniquePlaceholderEmail($provider, $providerUserId);
            $displayName = trim((string) ($profile['display_name'] ?? $username));
            $avatarUrl = isset($profile['avatar_url']) ? (string) $profile['avatar_url'] : null;
            $userId = $this->authService->createSocialUser($username, $finalEmail, $displayName, $avatarUrl);
        }

        $this->upsertAccount($userId, $provider, $providerUserId, $profile);
        return $userId;
    }

    private function findLinkedUserId(string $provider, string $providerUserId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT user_id
             FROM oauth_accounts
             WHERE provider = :provider
               AND provider_user_id = :provider_user_id
             LIMIT 1'
        );
        $stmt->execute([
            ':provider' => $provider,
            ':provider_user_id' => $providerUserId,
        ]);
        $row = $stmt->fetch();
        return $row ? (int) $row['user_id'] : null;
    }

    private function upsertAccount(int $userId, string $provider, string $providerUserId, array $profile): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO oauth_accounts (user_id, provider, provider_user_id, provider_username, email, avatar_url, profile_json)
             VALUES (:user_id, :provider, :provider_user_id, :provider_username, :email, :avatar_url, :profile_json)
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id),
               provider_username = VALUES(provider_username),
               email = VALUES(email),
               avatar_url = VALUES(avatar_url),
               profile_json = VALUES(profile_json),
               updated_at = NOW()'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':provider' => $provider,
            ':provider_user_id' => $providerUserId,
            ':provider_username' => (string) ($profile['username'] ?? ''),
            ':email' => strtolower(trim((string) ($profile['email'] ?? ''))) ?: null,
            ':avatar_url' => $profile['avatar_url'] ?? null,
            ':profile_json' => json_encode($profile, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function uniqueUsername(string $provider, string $rawUsername): string
    {
        $base = strtolower(trim($rawUsername));
        if ($base === '') {
            $base = $provider . '_player';
        }

        $base = preg_replace('/[^a-z0-9_]+/', '_', $base) ?? '';
        $base = trim($base, '_');
        if ($base === '') {
            $base = $provider . '_player';
        }
        if (strlen($base) < 3) {
            $base .= '_bot';
        }

        $base = substr($base, 0, 24);
        for ($i = 0; $i < 30; $i++) {
            $suffix = $i === 0 ? '' : '_' . substr(bin2hex(random_bytes(2)), 0, 4);
            $candidate = substr($base, 0, 32 - strlen($suffix)) . $suffix;
            if (!$this->authService->usernameExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not allocate a unique username.');
    }

    private function uniquePlaceholderEmail(string $provider, string $providerUserId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9]+/', '', $providerUserId) ?: 'user';
        $baseLocal = strtolower($provider . '_' . $safeId);

        for ($i = 0; $i < 30; $i++) {
            $local = $i === 0 ? $baseLocal : $baseLocal . '_' . $i;
            $email = $local . '@oauth.battlevue.local';
            if (!$this->authService->emailExists($email)) {
                return $email;
            }
        }

        throw new RuntimeException('Could not allocate a unique email for OAuth user.');
    }

    private function discordProfileFromCode(string $code): array
    {
        $config = $this->providerConfig('discord');

        $token = $this->requestJson('POST', 'https://discord.com/api/oauth2/token', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $config['redirect_uri'],
        ]);

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Discord token exchange failed.');
        }

        $user = $this->requestJson('GET', 'https://discord.com/api/users/@me', [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        $id = (string) ($user['id'] ?? '');
        $avatar = isset($user['avatar']) ? (string) $user['avatar'] : '';
        $avatarUrl = null;
        if ($id !== '' && $avatar !== '') {
            $avatarUrl = 'https://cdn.discordapp.com/avatars/' . rawurlencode($id) . '/' . rawurlencode($avatar) . '.png';
        }

        return [
            'provider_user_id' => $id,
            'username' => (string) ($user['username'] ?? ''),
            'display_name' => (string) (($user['global_name'] ?? '') !== '' ? $user['global_name'] : ($user['username'] ?? 'Discord User')),
            'email' => (string) ($user['email'] ?? ''),
            'avatar_url' => $avatarUrl,
            'raw' => $user,
        ];
    }

    private function githubProfileFromCode(string $code): array
    {
        $config = $this->providerConfig('github');

        $token = $this->requestJson('POST', 'https://github.com/login/oauth/access_token', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: BattleVueOAuth',
        ], [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $config['redirect_uri'],
        ]);

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('GitHub token exchange failed.');
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/vnd.github+json',
            'User-Agent: BattleVueOAuth',
        ];

        $user = $this->requestJson('GET', 'https://api.github.com/user', $headers);
        $email = (string) ($user['email'] ?? '');

        if ($email === '') {
            $emails = $this->requestJson('GET', 'https://api.github.com/user/emails', $headers);
            if (is_array($emails)) {
                foreach ($emails as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (!empty($entry['primary']) && !empty($entry['verified']) && !empty($entry['email'])) {
                        $email = (string) $entry['email'];
                        break;
                    }
                }
                if ($email === '') {
                    foreach ($emails as $entry) {
                        if (is_array($entry) && !empty($entry['email'])) {
                            $email = (string) $entry['email'];
                            break;
                        }
                    }
                }
            }
        }

        return [
            'provider_user_id' => (string) ($user['id'] ?? ''),
            'username' => (string) ($user['login'] ?? ''),
            'display_name' => (string) (($user['name'] ?? '') !== '' ? $user['name'] : ($user['login'] ?? 'GitHub User')),
            'email' => $email,
            'avatar_url' => isset($user['avatar_url']) ? (string) $user['avatar_url'] : null,
            'raw' => $user,
        ];
    }

    private function requestJson(string $method, string $url, array $headers = [], ?array $formParams = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('OAuth requires the PHP cURL extension.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize HTTP client.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($formParams !== null) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($formParams);
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('OAuth HTTP request failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded)
                ? (string) ($decoded['error_description'] ?? $decoded['message'] ?? 'OAuth provider returned an error.')
                : 'OAuth provider returned HTTP ' . $status;
            throw new RuntimeException($message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON from OAuth provider.');
        }

        return $decoded;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['discord', 'github'], true)) {
            throw new RuntimeException('Unsupported OAuth provider.');
        }
        return $provider;
    }

    private function providerConfig(string $provider): array
    {
        $baseUrl = rtrim((string) Config::get('APP_BASE_URL', ''), '/');

        if ($provider === 'discord') {
            $clientId = (string) Config::get('OAUTH_DISCORD_CLIENT_ID', '');
            $clientSecret = (string) Config::get('OAUTH_DISCORD_CLIENT_SECRET', '');
            $redirectUri = (string) Config::get('OAUTH_DISCORD_REDIRECT_URI', $baseUrl . '/api/auth/oauth/discord/callback');
        } else {
            $clientId = (string) Config::get('OAUTH_GITHUB_CLIENT_ID', '');
            $clientSecret = (string) Config::get('OAUTH_GITHUB_CLIENT_SECRET', '');
            $redirectUri = (string) Config::get('OAUTH_GITHUB_REDIRECT_URI', $baseUrl . '/api/auth/oauth/github/callback');
        }

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException(ucfirst($provider) . ' OAuth is not configured.');
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ];
    }

    private function issueState(string $provider): string
    {
        $state = bin2hex(random_bytes(16));
        $payload = [
            'provider' => $provider,
            'state' => $state,
            'exp' => time() + 600,
        ];

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            throw new RuntimeException('Failed to encode OAuth state.');
        }

        $encoded = $this->base64UrlEncode($jsonPayload);
        $signature = hash_hmac('sha256', $encoded, (string) Config::get('CSRF_SECRET', ''));
        $cookieValue = $encoded . '.' . $signature;

        $isSecure = strtolower((string) Config::get('APP_ENV', 'dev')) === 'prod';
        setcookie(self::STATE_COOKIE, $cookieValue, [
            'expires' => time() + 600,
            'path' => '/api/auth/oauth',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $state;
    }

    private function verifyState(string $provider, string $state): void
    {
        $cookie = (string) ($_COOKIE[self::STATE_COOKIE] ?? '');
        $this->clearStateCookie();

        if ($cookie === '') {
            throw new RuntimeException('Missing OAuth state.');
        }

        $parts = explode('.', $cookie, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid OAuth state format.');
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, (string) Config::get('CSRF_SECRET', ''));
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid OAuth state signature.');
        }

        $decoded = $this->base64UrlDecode($encoded);
        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid OAuth state payload.');
        }

        if (($payload['provider'] ?? '') !== $provider) {
            throw new RuntimeException('OAuth provider mismatch.');
        }
        if (($payload['state'] ?? '') !== $state) {
            throw new RuntimeException('OAuth state mismatch.');
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('OAuth state expired.');
        }
    }

    private function clearStateCookie(): void
    {
        $isSecure = strtolower((string) Config::get('APP_ENV', 'dev')) === 'prod';
        setcookie(self::STATE_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/api/auth/oauth',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url string.');
        }
        return $decoded;
    }
}
