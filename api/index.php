<?php

declare(strict_types=1);

use BattleVue\AuthService;
use BattleVue\Config;
use BattleVue\Csrf;
use BattleVue\Db;
use BattleVue\RateLimiter;
use BattleVue\Repos\BotRepo;
use BattleVue\Repos\LearningRepo;
use BattleVue\Repos\MatchRepo;
use BattleVue\Repos\NotificationRepo;
use BattleVue\Repos\QuestRepo;
use BattleVue\Repos\SocialRepo;
use BattleVue\Repos\UserRepo;
use BattleVue\Response;
use BattleVue\Services\AiTutorService;
use BattleVue\Services\BotService;
use BattleVue\Services\LearningService;
use BattleVue\Services\MatchService;
use BattleVue\Services\NotificationService;
use BattleVue\Services\OAuthService;
use BattleVue\Services\QuestService;
use BattleVue\Services\SocialService;
use BattleVue\Simulator\SimulatorV1;
use BattleVue\Validators\BlueprintValidator;
use BattleVue\Validators\RulesetValidator;

$bootstrapPath = __DIR__ . '/../src/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    $bootstrapPath = __DIR__ . '/src/bootstrap.php';
}
require_once $bootstrapPath;

function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function pathOnly(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    if (str_starts_with($path, '/api/')) {
        $path = substr($path, 4);
    } elseif ($path === '/api') {
        $path = '/';
    }
    return $path;
}

function enforceSameOrigin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $base = rtrim((string) Config::get('APP_BASE_URL', ''), '/');
    if ($base !== '' && $origin !== $base) {
        Response::error('Forbidden origin', 403);
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

function requireCsrf(array $sessionContext): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    if (!Csrf::verify((string) ($sessionContext['csrf_token'] ?? ''))) {
        Response::error('Invalid CSRF token', 403);
    }
}

function matchRoute(string $method, string $path, string $wantedMethod, string $pattern, ?array &$params = null): bool
{
    if ($method !== $wantedMethod) {
        return false;
    }
    if (!preg_match($pattern, $path, $matches)) {
        return false;
    }
    $params = $matches;
    return true;
}

function redirectTo(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

try {
    enforceSameOrigin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Internal-Sim-Key');
        header('Access-Control-Allow-Credentials: true');
        http_response_code(204);
        exit;
    }

    $db = Db::pdo();

    $auth = new AuthService($db);
    $rateLimiter = new RateLimiter();

    $userRepo = new UserRepo($db);
    $socialRepo = new SocialRepo($db);
    $questRepo = new QuestRepo($db);
    $botRepo = new BotRepo($db);
    $learningRepo = new LearningRepo($db);
    $matchRepo = new MatchRepo($db);
    $notificationRepo = new NotificationRepo($db);

    $socialService = new SocialService($userRepo, $socialRepo, $notificationRepo);
    $questService = new QuestService($questRepo);
    $botService = new BotService($botRepo, new BlueprintValidator(), new RulesetValidator());
    $learningService = new LearningService($learningRepo, new AiTutorService());
    $matchService = new MatchService($matchRepo, $botService, $socialRepo, $notificationRepo, new SimulatorV1());
    $notificationService = new NotificationService($notificationRepo);
    $oauthService = new OAuthService($db, $auth);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = pathOnly();
    $body = jsonBody();

    if (matchRoute($method, $path, 'POST', '#^/auth/register$#')) {
        $identityKey = ($_SERVER['REMOTE_ADDR'] ?? 'na') . ':register';
        if (!$rateLimiter->hit('auth', $identityKey, (int) Config::get('RATE_LIMIT_AUTH_WINDOW_SECONDS', 900), (int) Config::get('RATE_LIMIT_AUTH_MAX', 20))) {
            Response::error('Too many requests', 429);
        }

        $username = trim((string) ($body['username'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
            Response::error('Invalid username', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email', 422);
        }
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters', 422);
        }

        try {
            $userId = $auth->register($username, $email, $password);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                Response::error('Username or email already exists', 422);
            }
            throw $e;
        }
        Response::ok(['user_id' => $userId]);
    }

    if (matchRoute($method, $path, 'POST', '#^/auth/login$#')) {
        $identityKey = ($_SERVER['REMOTE_ADDR'] ?? 'na') . ':login';
        if (!$rateLimiter->hit('auth', $identityKey, (int) Config::get('RATE_LIMIT_AUTH_WINDOW_SECONDS', 900), (int) Config::get('RATE_LIMIT_AUTH_MAX', 20))) {
            Response::error('Too many requests', 429);
        }

        $identity = trim((string) ($body['identity'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($identity === '' || $password === '') {
            Response::error('Missing credentials', 422);
        }

        $user = $auth->login($identity, $password);
        if (!$user) {
            Response::error('Invalid credentials', 401);
        }

        Response::ok(['user' => $user]);
    }

    if (matchRoute($method, $path, 'POST', '#^/auth/logout$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $auth->logout();
        Response::ok();
    }

    if (matchRoute($method, $path, 'GET', '#^/auth/me$#')) {
        $ctx = $auth->currentUser();
        if (!$ctx) {
            Response::ok(['user' => null]);
        }
        Response::ok(['user' => $ctx['user']]);
    }

    if (matchRoute($method, $path, 'GET', '#^/auth/oauth/(discord|github)/start$#', $params)) {
        $provider = (string) $params[1];
        try {
            $authUrl = $oauthService->authorizationUrl($provider);
            redirectTo($authUrl);
        } catch (Throwable $e) {
            $base = rtrim((string) Config::get('APP_BASE_URL', ''), '/');
            redirectTo($base . '/?oauth_error=' . rawurlencode($e->getMessage()));
        }
    }

    if (matchRoute($method, $path, 'GET', '#^/auth/oauth/(discord|github)/url$#', $params)) {
        $provider = (string) $params[1];
        Response::ok([
            'provider' => $provider,
            'auth_url' => $oauthService->authorizationUrl($provider),
        ]);
    }

    if (matchRoute($method, $path, 'GET', '#^/auth/oauth/(discord|github)/callback$#', $params)) {
        $provider = (string) $params[1];
        $code = trim((string) ($_GET['code'] ?? ''));
        $state = trim((string) ($_GET['state'] ?? ''));
        $base = rtrim((string) Config::get('APP_BASE_URL', ''), '/');

        try {
            $oauthService->authenticateFromCallback($provider, $code, $state);
            redirectTo($base . '/?oauth=success');
        } catch (Throwable $e) {
            redirectTo($base . '/?oauth_error=' . rawurlencode($e->getMessage()));
        }
    }

    // Social
    if (matchRoute($method, $path, 'GET', '#^/users/search$#')) {
        $ctx = $auth->requireUser();
        $q = trim((string) ($_GET['q'] ?? ''));
        Response::ok(['users' => $socialService->searchUsers((int) $ctx['user']['id'], $q)]);
    }

    if (matchRoute($method, $path, 'POST', '#^/friends/request$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $receiverId = (int) ($body['receiver_user_id'] ?? 0);
        $requestId = $socialService->sendFriendRequest((int) $ctx['user']['id'], $receiverId);
        Response::ok(['request_id' => $requestId]);
    }

    if (matchRoute($method, $path, 'POST', '#^/friends/respond$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $requestId = (int) ($body['request_id'] ?? 0);
        $action = (string) ($body['action'] ?? '');
        $socialService->respondFriendRequest((int) $ctx['user']['id'], $requestId, $action);
        Response::ok();
    }

    if (matchRoute($method, $path, 'GET', '#^/friends/list$#')) {
        $ctx = $auth->requireUser();
        Response::ok($socialService->listFriends((int) $ctx['user']['id']));
    }

    if (matchRoute($method, $path, 'POST', '#^/friends/remove$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $friendId = (int) ($body['friend_user_id'] ?? 0);
        $socialService->removeFriend((int) $ctx['user']['id'], $friendId);
        Response::ok();
    }

    if (matchRoute($method, $path, 'POST', '#^/blocks/add$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $blockedId = (int) ($body['blocked_user_id'] ?? 0);
        $socialService->addBlock((int) $ctx['user']['id'], $blockedId);
        Response::ok();
    }

    // Quests
    if (matchRoute($method, $path, 'GET', '#^/tracks$#')) {
        $auth->requireUser();
        Response::ok(['tracks' => $questService->tracks()]);
    }

    if (matchRoute($method, $path, 'GET', '#^/quests$#')) {
        $auth->requireUser();
        $track = isset($_GET['track']) ? trim((string) $_GET['track']) : null;
        Response::ok(['quests' => $questService->quests($track)]);
    }

    if (matchRoute($method, $path, 'GET', '#^/quest/(\d+)$#', $params)) {
        $auth->requireUser();
        Response::ok(['quest' => $questService->quest((int) $params[1])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/quest/(\d+)/submit-step$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $stepIndex = (int) ($body['step_index'] ?? 0);
        $submission = is_array($body['submission'] ?? null) ? $body['submission'] : [];
        $result = $questService->submitStep((int) $ctx['user']['id'], (int) $params[1], $stepIndex, $submission);
        Response::ok($result);
    }

    if (matchRoute($method, $path, 'POST', '#^/quest/(\d+)/complete$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $result = $questService->completeQuest((int) $ctx['user']['id'], (int) $params[1]);
        Response::ok($result);
    }

    // Learning quests (AI tutor + token checkpoints)
    if (matchRoute($method, $path, 'GET', '#^/learn/topics$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['topics' => $learningService->listTopics((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/learn/topics/custom$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $title = trim((string) ($body['title'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        Response::ok($learningService->createCustomTopic((int) $ctx['user']['id'], $title, $description));
    }

    if (matchRoute($method, $path, 'POST', '#^/learn/sessions/start$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $topicId = (int) ($body['topic_id'] ?? 0);
        if ($topicId <= 0) {
            Response::error('topic_id is required', 422);
        }
        Response::ok($learningService->startSession((int) $ctx['user']['id'], $topicId));
    }

    if (matchRoute($method, $path, 'GET', '#^/learn/sessions/(\d+)$#', $params)) {
        $ctx = $auth->requireUser();
        Response::ok($learningService->getSession((int) $ctx['user']['id'], (int) $params[1]));
    }

    if (matchRoute($method, $path, 'POST', '#^/learn/sessions/(\d+)/message$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $message = trim((string) ($body['message'] ?? ''));
        Response::ok($learningService->sendMessage((int) $ctx['user']['id'], (int) $params[1], $message));
    }

    if (matchRoute($method, $path, 'POST', '#^/learn/sessions/(\d+)/checkpoint/submit$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];
        Response::ok($learningService->submitCheckpoint((int) $ctx['user']['id'], (int) $params[1], $answers));
    }

    // Inventory / Bots
    if (matchRoute($method, $path, 'GET', '#^/inventory$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['inventory' => $botService->inventory((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/blueprints/create$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $id = $botService->createBlueprint((int) $ctx['user']['id'], $body);
        Response::ok(['blueprint_id' => $id]);
    }

    if (matchRoute($method, $path, 'POST', '#^/blueprints/update$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $id = (int) ($body['blueprint_id'] ?? 0);
        $botService->updateBlueprint((int) $ctx['user']['id'], $id, $body);
        Response::ok();
    }

    if (matchRoute($method, $path, 'GET', '#^/blueprints/list$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['blueprints' => $botService->listBlueprints((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/rulesets/create$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $id = $botService->createRuleset((int) $ctx['user']['id'], $body);
        Response::ok(['ruleset_id' => $id]);
    }

    if (matchRoute($method, $path, 'POST', '#^/rulesets/update$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $id = (int) ($body['ruleset_id'] ?? 0);
        $botService->updateRuleset((int) $ctx['user']['id'], $id, $body);
        Response::ok();
    }

    if (matchRoute($method, $path, 'GET', '#^/rulesets/list$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['rulesets' => $botService->listRulesets((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/validate/blueprint$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        Response::ok($botService->validateBlueprint($body));
    }

    if (matchRoute($method, $path, 'POST', '#^/validate/ruleset$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        Response::ok($botService->validateRuleset($body));
    }

    // Matches
    if (matchRoute($method, $path, 'POST', '#^/matches/queue$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $mode = (string) ($body['mode'] ?? 'casual');
        Response::ok($matchService->queue((int) $ctx['user']['id'], $mode));
    }

    if (matchRoute($method, $path, 'POST', '#^/matches/challenge$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $target = (int) ($body['target_user_id'] ?? 0);
        Response::ok($matchService->challenge((int) $ctx['user']['id'], $target));
    }

    if (matchRoute($method, $path, 'POST', '#^/matches/(\d+)/submit$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $blueprintId = (int) ($body['blueprint_id'] ?? 0);
        $rulesetId = (int) ($body['ruleset_id'] ?? 0);
        Response::ok($matchService->submitLoadout((int) $ctx['user']['id'], (int) $params[1], $blueprintId, $rulesetId));
    }

    if (matchRoute($method, $path, 'POST', '#^/matches/(\d+)/simulate$#', $params)) {
        $key = $_SERVER['HTTP_X_INTERNAL_SIM_KEY'] ?? '';
        if (!hash_equals((string) Config::get('INTERNAL_SIMULATE_KEY', ''), $key)) {
            Response::error('Forbidden', 403);
        }
        Response::ok($matchService->simulate((int) $params[1]));
    }

    if (matchRoute($method, $path, 'GET', '#^/matches/history$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['matches' => $matchService->history((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'GET', '#^/matches/(\d+)/replay$#', $params)) {
        $ctx = $auth->requireUser();
        Response::ok($matchService->replay((int) $ctx['user']['id'], (int) $params[1]));
    }

    // Match chat
    if (matchRoute($method, $path, 'GET', '#^/matches/(\d+)/messages$#', $params)) {
        $ctx = $auth->requireUser();
        $afterId = (int) ($_GET['after_id'] ?? 0);
        Response::ok(['messages' => $matchService->messages((int) $ctx['user']['id'], (int) $params[1], $afterId)]);
    }

    if (matchRoute($method, $path, 'POST', '#^/matches/(\d+)/messages$#', $params)) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $chatKey = (string) $ctx['user']['id'] . ':match:' . (string) $params[1];
        if (!$rateLimiter->hit('chat', $chatKey, (int) Config::get('RATE_LIMIT_CHAT_WINDOW_SECONDS', 60), (int) Config::get('RATE_LIMIT_CHAT_MAX', 30))) {
            Response::error('Too many chat messages', 429);
        }
        $message = (string) ($body['message'] ?? '');
        Response::ok($matchService->addMessage((int) $ctx['user']['id'], (int) $params[1], $message));
    }

    // Notifications
    if (matchRoute($method, $path, 'GET', '#^/notifications$#')) {
        $ctx = $auth->requireUser();
        Response::ok(['notifications' => $notificationService->list((int) $ctx['user']['id'])]);
    }

    if (matchRoute($method, $path, 'POST', '#^/notifications/read$#')) {
        $ctx = $auth->requireUser();
        requireCsrf($ctx);
        $ids = is_array($body['ids'] ?? null) ? $body['ids'] : [];
        $notificationService->read((int) $ctx['user']['id'], $ids);
        Response::ok();
    }

    Response::error('Not found', 404);
} catch (Throwable $e) {
    $code = $e instanceof RuntimeException ? 422 : 500;
    Response::error($e->getMessage(), $code);
}
