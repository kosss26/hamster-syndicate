<?php

declare(strict_types=1);

/**
 * API для Telegram Mini App
 * 
 * Этот файл обрабатывает HTTP-запросы от Mini App и взаимодействует
 * с существующими сервисами бота.
 */

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Infrastructure\Config\Config;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Application\Services\TelegramPhotoService;
use QuizBot\Application\Services\AchievementService;
use QuizBot\Application\Services\AchievementTrackerService;
use QuizBot\Application\Services\CollectionService;
use QuizBot\Application\Services\TicketService;

// Загрузка автолоадера
require dirname(__DIR__) . '/vendor/autoload.php';

// CORS заголовки для Mini App
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = '';
$allowedOriginMatched = false;

try {
    $tmpConfig = Config::fromEnv(dirname(__DIR__) . '/config');
    $configuredWebappUrl = (string) $tmpConfig->get('WEBAPP_URL', '');
    $allowedOrigin = $configuredWebappUrl !== '' ? rtrim($configuredWebappUrl, '/') : '';
} catch (Throwable $e) {
    $allowedOrigin = '';
    $allowedOriginMatched = false;
}

if ($allowedOrigin !== '' && $origin !== '') {
    $allowedParts = parse_url($allowedOrigin);
    $originParts = parse_url($origin);

    $sameHost = isset($allowedParts['host'], $originParts['host'])
        && strtolower((string) $allowedParts['host']) === strtolower((string) $originParts['host']);
    $sameScheme = (($allowedParts['scheme'] ?? 'https') === ($originParts['scheme'] ?? 'https'));
    $allowedPort = $allowedParts['port'] ?? (($allowedParts['scheme'] ?? 'https') === 'http' ? 80 : 443);
    $originPort = $originParts['port'] ?? (($originParts['scheme'] ?? 'https') === 'http' ? 80 : 443);
    $samePort = ($allowedPort === $originPort);

    if ($sameHost && $sameScheme && $samePort) {
        $allowedOriginMatched = true;
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$requestId = bin2hex(random_bytes(8));
header('X-Request-Id: ' . $requestId);

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin !== '' && !$allowedOriginMatched) {
        http_response_code(403);
        exit;
    }

    http_response_code(200);
    exit;
}

// Инициализация приложения
try {
    $app = new AppBootstrap(dirname(__DIR__));
    $container = $app->getContainer();
} catch (Throwable $e) {
    jsonError('Ошибка инициализации: ' . $e->getMessage(), 500);
}

// Получение и парсинг маршрута
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Убираем /api из начала пути
$path = preg_replace('#^/api#', '', $requestUri);
$path = $path ?: '/';

// Получение тела запроса
$rawBody = file_get_contents('php://input');
$body = [];

if ($rawBody !== false && trim($rawBody) !== '') {
    $decodedBody = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Некорректный JSON в теле запроса', 400);
    }

    if (is_array($decodedBody)) {
        $body = $decodedBody;
    }
}

// Верификация Telegram initData
$initData = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '';
/** @var Config $config */
$config = $container->get(Config::class);
$initDataTtlSeconds = max(3600, (int) $config->get('INIT_DATA_TTL_SECONDS', 86400));
$telegramUser = verifyTelegramInitData($initData, $config->get('TELEGRAM_BOT_TOKEN', ''), $initDataTtlSeconds);
$isDevEnv = in_array((string) $config->get('APP_ENV', 'production'), ['development', 'local'], true);

if (!$telegramUser) {
    $telegramUser = resolveDevTelegramUser($config, $isDevEnv);
}

if ($telegramUser) {
    // Обновляем время последней активности пользователя
    try {
        \QuizBot\Domain\Model\User::query()
            ->where('telegram_id', $telegramUser['id'])
            ->update(['updated_at' => \Illuminate\Support\Carbon::now()]);
    } catch (Throwable $e) {
        // Игнорируем ошибки обновления активности, чтобы не ломать основной запрос
    }
}

require_once __DIR__ . '/handlers/misc_handlers.php';
require_once __DIR__ . '/handlers/shop_handlers.php';
require_once __DIR__ . '/handlers/achievements_handlers.php';
require_once __DIR__ . '/handlers/duel_handlers.php';
require_once __DIR__ . '/handlers/truefalse_handlers.php';
require_once __DIR__ . '/handlers/admin_handlers.php';
require_once __DIR__ . '/handlers/user_handlers.php';

// Роутинг
try {
    switch (true) {
        // GET /debug - отладка авторизации
        case $path === '/debug' && $requestMethod === 'GET' && $isDevEnv:
            jsonResponse([
                'initData_received' => !empty($initData),
                'initData_length' => strlen($initData),
                'initData_preview' => substr($initData, 0, 100),
                'telegram_user' => $telegramUser,
                'headers' => [
                    'X-Telegram-Init-Data' => $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? 'not set',
                ],
            ]);
            break;

        // GET /user - получить текущего пользователя
        case $path === '/user' && $requestMethod === 'GET':
            handleGetUser($container, $telegramUser);
            break;

        // GET /profile - получить профиль
        case $path === '/profile' && $requestMethod === 'GET':
            handleGetProfile($container, $telegramUser);
            break;

        // POST /duel/create - создать дуэль
        case $path === '/duel/create' && $requestMethod === 'POST':
            handleCreateDuel($container, $telegramUser, $body);
            break;

        // GET /duel/current - получить текущую активную дуэль
        case $path === '/duel/current' && $requestMethod === 'GET':
            handleGetActiveDuel($container, $telegramUser);
            break;

        // GET /duel/ws-ticket - получить подписанный тикет для WebSocket
        case $path === '/duel/ws-ticket' && $requestMethod === 'GET':
            handleGetDuelWsTicket($container, $telegramUser, isset($_GET['duel_id']) ? (int) $_GET['duel_id'] : null);
            break;

        // GET /duel/{id} - получить информацию о дуэли
        case preg_match('#^/duel/(\d+)$#', $path, $matches) && $requestMethod === 'GET':
            handleGetDuel($container, $telegramUser, (int) $matches[1]);
            break;

        // POST /duel/answer - ответить на вопрос дуэли
        case $path === '/duel/answer' && $requestMethod === 'POST':
            handleDuelAnswer($container, $telegramUser, $body);
            break;

        // POST /duel/hint - использовать подсказку в дуэли
        case $path === '/duel/hint' && $requestMethod === 'POST':
            handleDuelHint($container, $telegramUser, $body);
            break;

        // POST /duel/join - присоединиться к дуэли по коду
        case $path === '/duel/join' && $requestMethod === 'POST':
            handleJoinDuel($container, $telegramUser, $body);
            break;

        // POST /duel/{id}/cancel - отменить свою дуэль
        case preg_match('#^/duel/(\d+)/cancel$#', $path, $matches) && $requestMethod === 'POST':
            handleCancelDuel($container, $telegramUser, (int) $matches[1]);
            break;

        // GET /duel/rematch/incoming - входящее приглашение на реванш
        case $path === '/duel/rematch/incoming' && $requestMethod === 'GET':
            handleGetIncomingRematch($container, $telegramUser);
            break;

        // POST /duel/rematch/accept - принять реванш
        case $path === '/duel/rematch/accept' && $requestMethod === 'POST':
            handleAcceptRematch($container, $telegramUser, $body);
            break;

        // POST /duel/rematch/decline - отклонить реванш
        case $path === '/duel/rematch/decline' && $requestMethod === 'POST':
            handleDeclineRematch($container, $telegramUser, $body);
            break;

        // POST /duel/rematch/cancel - отменить отправленный реванш
        case $path === '/duel/rematch/cancel' && $requestMethod === 'POST':
            handleCancelRematch($container, $telegramUser, $body);
            break;

        // GET /truefalse/question - получить вопрос "Правда или ложь"
        case $path === '/truefalse/question' && $requestMethod === 'GET':
            handleGetTrueFalseQuestion($container, $telegramUser);
            break;

        // POST /truefalse/answer - ответить на вопрос "Правда или ложь"
        case $path === '/truefalse/answer' && $requestMethod === 'POST':
            handleTrueFalseAnswer($container, $telegramUser, $body);
            break;

        // GET /leaderboard - получить рейтинг
        case $path === '/leaderboard' && $requestMethod === 'GET':
            handleGetLeaderboard($container, $_GET['type'] ?? 'duel');
            break;

        // GET /statistics - получить расширенную статистику
        case $path === '/statistics' && $requestMethod === 'GET':
            handleGetStatistics($container, $telegramUser);
            break;

        // GET /statistics/quick - получить краткую статистику
        case $path === '/statistics/quick' && $requestMethod === 'GET':
            handleGetQuickStatistics($container, $telegramUser);
            break;

        // GET /referral/stats - получить реферальную статистику
        case $path === '/referral/stats' && $requestMethod === 'GET':
            handleGetReferralStats($container, $telegramUser);
            break;

        // POST /support/message - отправить сообщение в поддержку
        case $path === '/support/message' && $requestMethod === 'POST':
            handleSupportMessage($container, $telegramUser, $body);
            break;

        // GET /admin/check - проверить права админа
        case $path === '/admin/check' && $requestMethod === 'GET':
            handleAdminCheck($container, $telegramUser);
            break;

        // GET /admin/stats - получить статистику для админки
        case $path === '/admin/stats' && $requestMethod === 'GET':
            handleAdminStats($container, $telegramUser);
            break;

        // GET /admin/analytics/categories - аналитика по категориям
        case $path === '/admin/analytics/categories' && $requestMethod === 'GET':
            handleAdminCategoryAnalytics($container, $telegramUser, $_GET);
            break;

        // GET /admin/analytics/questions - аналитика по вопросам
        case $path === '/admin/analytics/questions' && $requestMethod === 'GET':
            handleAdminQuestionAnalytics($container, $telegramUser, $_GET);
            break;

        // GET /admin/users - список пользователей с фильтрами
        case $path === '/admin/users' && $requestMethod === 'GET':
            handleAdminUsers($container, $telegramUser, $_GET);
            break;

        // GET /admin/duels - список дуэлей с фильтрами
        case $path === '/admin/duels' && $requestMethod === 'GET':
            handleAdminDuels($container, $telegramUser, $_GET);
            break;

        // GET /admin/duels/{id} - подробная информация по дуэли
        case preg_match('#^/admin/duels/(\d+)$#', $path, $matches) && $requestMethod === 'GET':
            handleAdminDuelDetails($container, $telegramUser, (int) $matches[1]);
            break;

        // GET /online - получить онлайн
        case $path === '/online' && $requestMethod === 'GET':
            handleGetOnline($container, $telegramUser);
            break;

        // GET /notifications/admin - получить рассылки администрации для игроков
        case $path === '/notifications/admin' && $requestMethod === 'GET':
            handleGetAdminNotificationsFeed($container, $telegramUser, $_GET);
            break;

        // POST /admin/duel/{id}/cancel - отменить дуэль
        case preg_match('#^/admin/duel/(\d+)/cancel$#', $path, $matches) && $requestMethod === 'POST':
            handleAdminCancelDuel($container, $telegramUser, (int) $matches[1]);
            break;

        // POST /admin/duels/cancel-all - отменить все активные дуэли
        case $path === '/admin/duels/cancel-all' && $requestMethod === 'POST':
            handleAdminCancelAllDuels($container, $telegramUser);
            break;

        // POST /admin/duel/by-code/cancel - отменить дуэль по коду
        case $path === '/admin/duel/by-code/cancel' && $requestMethod === 'POST':
            handleAdminCancelDuelByCode($container, $telegramUser, $body);
            break;

        // POST /admin/question - добавить вопрос
        case $path === '/admin/question' && $requestMethod === 'POST':
            handleAdminAddQuestion($container, $telegramUser, $body);
            break;

        // GET /admin/facts - список фактов "Правда или ложь"
        case $path === '/admin/facts' && $requestMethod === 'GET':
            handleAdminFacts($container, $telegramUser, $_GET);
            break;

        // POST /admin/fact - добавить факт "Правда или ложь"
        case $path === '/admin/fact' && $requestMethod === 'POST':
            handleAdminAddFact($container, $telegramUser, $body);
            break;

        // POST /admin/fact/{id}/toggle - включить/выключить факт
        case preg_match('#^/admin/fact/(\d+)/toggle$#', $path, $matches) && $requestMethod === 'POST':
            handleAdminToggleFact($container, $telegramUser, (int) $matches[1], $body);
            break;

        // GET /admin/notifications - список рассылок для админки
        case $path === '/admin/notifications' && $requestMethod === 'GET':
            handleAdminNotificationsList($container, $telegramUser, $_GET);
            break;

        // POST /admin/notifications/broadcast - создать рассылку
        case $path === '/admin/notifications/broadcast' && $requestMethod === 'POST':
            handleAdminBroadcastNotification($container, $telegramUser, $body);
            break;

        // POST /admin/lootbox/grant - выдать лутбоксы игроку
        case $path === '/admin/lootbox/grant' && $requestMethod === 'POST':
            handleAdminGrantLootbox($container, $telegramUser, $body);
            break;

        // === SHOP SYSTEM ===
        
        // GET /shop/items - получить товары магазина
        case $path === '/shop/items' && $requestMethod === 'GET':
            handleGetShopItems($container, $telegramUser, $_GET['category'] ?? null);
            break;

        // POST /shop/purchase - купить товар
        case $path === '/shop/purchase' && $requestMethod === 'POST':
            handleShopPurchase($container, $telegramUser, $body);
            break;

        // GET /shop/history - история покупок
        case $path === '/shop/history' && $requestMethod === 'GET':
            handleShopHistory($container, $telegramUser);
            break;

        // GET /inventory - получить инвентарь
        case $path === '/inventory' && $requestMethod === 'GET':
            handleGetInventory($container, $telegramUser);
            break;

        // POST /inventory/equip - экипировать косметику
        case $path === '/inventory/equip' && $requestMethod === 'POST':
            handleEquipCosmetic($container, $telegramUser, $body);
            break;

        // POST /inventory/unequip - снять косметику
        case $path === '/inventory/unequip' && $requestMethod === 'POST':
            handleUnequipCosmetic($container, $telegramUser, $body);
            break;

        // GET /wheel/status - статус колеса фортуны
        case $path === '/wheel/status' && $requestMethod === 'GET':
            handleWheelStatus($container, $telegramUser);
            break;

        // POST /wheel/spin - крутить колесо
        case $path === '/wheel/spin' && $requestMethod === 'POST':
            handleWheelSpin($container, $telegramUser, $body);
            break;

        // GET /wheel/config - конфигурация колеса
        case $path === '/wheel/config' && $requestMethod === 'GET':
            handleWheelConfig($container);
            break;

        // POST /lootbox/open - открыть лутбокс
        case $path === '/lootbox/open' && $requestMethod === 'POST':
            handleLootboxOpen($container, $telegramUser, $body);
            break;

        // GET /lootbox/config - конфигурация и прогресс гарантов
        case $path === '/lootbox/config' && $requestMethod === 'GET':
            handleLootboxConfig($container, $telegramUser);
            break;

        // GET /lootbox/history - история открытых лутбоксов
        case $path === '/lootbox/history' && $requestMethod === 'GET':
            handleLootboxHistory($container, $telegramUser);
            break;

        // GET /boosts - получить активные бусты
        case $path === '/boosts' && $requestMethod === 'GET':
            handleGetBoosts($container, $telegramUser);
            break;

        // GET /images/{folder}/{filename} - статические изображения
        case preg_match('#^/images/([a-z_]+)/(.+)$#', $path, $matches) && $requestMethod === 'GET':
            handleGetImage($matches[1], $matches[2]);
            break;

        // GET /achievements - все достижения
        case $path === '/achievements' && $requestMethod === 'GET':
            handleGetAchievements($container);
            break;

        // GET /achievements/my - мои достижения с прогрессом
        case $path === '/achievements/my' && $requestMethod === 'GET':
            handleGetMyAchievements($container, $telegramUser);
            break;

        // GET /achievements/showcased - витрина достижений
        case $path === '/achievements/showcased' && $requestMethod === 'GET':
            handleGetShowcasedAchievements($container, $telegramUser);
            break;

        // POST /achievements/showcase - настроить витрину
        case $path === '/achievements/showcase' && $requestMethod === 'POST':
            handleSetShowcasedAchievements($container, $telegramUser, $body);
            break;

        // GET /achievements/stats - статистика достижений
        case $path === '/achievements/stats' && $requestMethod === 'GET':
            handleGetAchievementStats($container, $telegramUser);
            break;

        // GET /collections - все коллекции с прогрессом
        case $path === '/collections' && $requestMethod === 'GET':
            handleGetCollections($container, $telegramUser);
            break;

        // GET /collections/{id}/items - карточки коллекции
        case preg_match('#^/collections/(\d+)/items$#', $path, $matches) && $requestMethod === 'GET':
            handleGetCollectionItems($container, $telegramUser, (int) $matches[1]);
            break;

        default:
            jsonError('Маршрут не найден', 404);
    }
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Верификация initData из Telegram
 */
function verifyTelegramInitData(string $initData, string $botToken, int $ttlSeconds = 300): ?array
{
    if (empty($initData) || $botToken === '') {
        return null;
    }

    // Парсим initData
    parse_str($initData, $data);

    if (!isset($data['user'], $data['auth_date'], $data['hash'])) {
        return null;
    }

    if (!is_numeric($data['auth_date'])) {
        return null;
    }

    $now = time();
    $authDate = (int) $data['auth_date'];
    $clockSkewAllowanceSeconds = 30;

    if (($now - $authDate) > $ttlSeconds || $authDate > ($now + $clockSkewAllowanceSeconds)) {
        return null;
    }

    // Проверяем подпись hash
    $checkHash = (string) $data['hash'];
    $dataForCheck = $data;
    unset($dataForCheck['hash']);

    ksort($dataForCheck);
    $dataCheckString = implode("\n", array_map(
        static fn($key, $value) => "$key=$value",
        array_keys($dataForCheck),
        array_values($dataForCheck)
    ));

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($hash, $checkHash)) {
        error_log('Warning: Telegram initData signature mismatch');
        return null;
    }

    $decodedUser = json_decode((string) $data['user'], true);

    if (!is_array($decodedUser) || !isset($decodedUser['id'])) {
        return null;
    }

    return $decodedUser;
}

/**
 * Development-only fallback auth for local WebApp launches outside Telegram.
 *
 * Enabled only when APP_ENV is local/development and DEV_AUTH_ENABLED=true.
 * User id source priority:
 *  1) X-Dev-Telegram-User-Id header
 *  2) DEV_AUTH_USER_ID env
 */
function resolveDevTelegramUser(Config $config, bool $isDevEnv): ?array
{
    if (!$isDevEnv || !filter_var((string) $config->get('DEV_AUTH_ENABLED', false), FILTER_VALIDATE_BOOL)) {
        return null;
    }

    $headerUserId = $_SERVER['HTTP_X_DEV_TELEGRAM_USER_ID'] ?? '';
    $envUserId = (string) $config->get('DEV_AUTH_USER_ID', '');
    $userIdRaw = trim($headerUserId !== '' ? $headerUserId : $envUserId);

    if ($userIdRaw === '' || !ctype_digit($userIdRaw)) {
        return null;
    }

    $userId = (int) $userIdRaw;
    if ($userId <= 0) {
        return null;
    }

    return [
        'id' => $userId,
        'username' => 'dev_user_' . $userId,
        'first_name' => 'Dev',
        'last_name' => 'User',
    ];
}

function isAdmin(?array $telegramUser, $container): bool
{
    if (!$telegramUser) {
        return false;
    }
    
    /** @var Config $config */
    $config = $container->get(Config::class);
    $adminIdsRaw = $config->get('ADMIN_TELEGRAM_IDS', '');
    $adminIds = array_map('trim', explode(',', (string) $adminIdsRaw));
    
    return in_array((string) $telegramUser['id'], $adminIds, true);
}
/**
 * Отправка JSON ответа
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    global $requestId;
    echo json_encode([
        'success' => true,
        'request_id' => $requestId ?? null,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Отправка JSON ошибки
 */
function jsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    global $requestId;
    echo json_encode([
        'success' => false,
        'request_id' => $requestId ?? null,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
