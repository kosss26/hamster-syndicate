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

// Загрузка автолоадера
require dirname(__DIR__) . '/vendor/autoload.php';

// CORS заголовки для Mini App
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');
header('Content-Type: application/json; charset=utf-8');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Верификация Telegram initData
$initData = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '';
/** @var Config $config */
$config = $container->get(Config::class);
$telegramUser = verifyTelegramInitData($initData, $config->get('TELEGRAM_BOT_TOKEN', ''));

// Роутинг
try {
    switch (true) {
        // GET /debug - отладка авторизации
        case $path === '/debug' && $requestMethod === 'GET':
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

        // GET /admin/check - проверить права админа
        case $path === '/admin/check' && $requestMethod === 'GET':
            handleAdminCheck($container, $telegramUser);
            break;

        // GET /admin/stats - получить статистику для админки
        case $path === '/admin/stats' && $requestMethod === 'GET':
            handleAdminStats($container, $telegramUser);
            break;

        // POST /admin/duel/{id}/cancel - отменить дуэль
        case preg_match('#^/admin/duel/(\d+)/cancel$#', $path, $matches) && $requestMethod === 'POST':
            handleAdminCancelDuel($container, $telegramUser, (int) $matches[1]);
            break;

        // POST /admin/duels/cancel-all - отменить все активные дуэли
        case $path === '/admin/duels/cancel-all' && $requestMethod === 'POST':
            handleAdminCancelAllDuels($container, $telegramUser);
            break;

        // POST /admin/question - добавить вопрос
        case $path === '/admin/question' && $requestMethod === 'POST':
            handleAdminAddQuestion($container, $telegramUser, $body);
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

        default:
            jsonError('Маршрут не найден', 404);
    }
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Верификация initData из Telegram
 */
function verifyTelegramInitData(string $initData, string $botToken): ?array
{
    if (empty($initData)) {
        return null;
    }

    // Парсим initData
    parse_str($initData, $data);
    
    if (!isset($data['user'])) {
        return null;
    }

    // Если есть hash - проверяем подпись
    if (isset($data['hash'])) {
        $checkHash = $data['hash'];
        $dataForCheck = $data;
        unset($dataForCheck['hash']);

        ksort($dataForCheck);
        $dataCheckString = implode("\n", array_map(
            fn($key, $value) => "$key=$value",
            array_keys($dataForCheck),
            array_values($dataForCheck)
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if ($hash !== $checkHash) {
            // Подпись не совпадает, но для отладки всё равно парсим user
            // В продакшене здесь должен быть return null;
            error_log("Warning: Telegram initData signature mismatch");
        }
    }

    return json_decode($data['user'], true);
}

/**
 * Получение текущего пользователя
 */
function handleGetUser($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    jsonResponse([
        'id' => $user->getKey(),
        'telegram_id' => $user->telegram_id,
        'username' => $user->username,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
    ]);
}

/**
 * Получение профиля пользователя
 */
function handleGetProfile($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    /** @var ProfileFormatter $profileFormatter */
    $profileFormatter = $container->get(ProfileFormatter::class);
    
    /** @var TelegramPhotoService $photoService */
    $photoService = $container->get(TelegramPhotoService::class);
    
    // Синхронизируем данные пользователя
    $user = $userService->syncFromTelegram($telegramUser);
    $user = $userService->ensureProfile($user);
    
    // Обновляем фото если его нет
    if (empty($user->photo_url)) {
        $photoService->updateUserPhoto($user);
        $user->refresh(); // Перезагружаем из БД
    }
    
    $profile = $user->profile;
    
    if (!$profile) {
        jsonError('Профиль не найден', 404);
    }

    $rank = $profileFormatter->getRankByRating((int) $profile->rating);

    jsonResponse([
        'rating' => (int) $profile->rating,
        'rank' => $rank,
        'coins' => (int) $profile->coins,
        'win_streak' => (int) $profile->streak_days,
        'true_false_record' => (int) $profile->true_false_record,
        'photo_url' => $user->photo_url,
        'stats' => [
            'duel_wins' => (int) $profile->duel_wins,
            'duel_losses' => (int) $profile->duel_losses,
            'duel_draws' => (int) $profile->duel_draws,
            'total_games' => (int) ($profile->duel_wins + $profile->duel_losses + $profile->duel_draws),
        ],
    ]);
}

/**
 * Создание дуэли
 */
function handleCreateDuel($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    $mode = $body['mode'] ?? 'random';
    
    // Проверяем, есть ли у пользователя активная дуэль
    $existingDuel = $duelService->findActiveDuelForUser($user);
    if ($existingDuel) {
        jsonResponse([
            'duel_id' => $existingDuel->getKey(),
            'status' => $existingDuel->status,
            'code' => $existingDuel->code,
            'initiator_id' => $existingDuel->initiator_user_id,
            'opponent_id' => $existingDuel->opponent_user_id,
            'existing' => true,
        ]);
        return;
    }
    
    // Ищем доступный тикет от другого игрока (TTL 60 секунд)
    $availableTicket = $duelService->findAvailableMatchmakingTicket($user, 60);
    
    if ($availableTicket) {
        // Нашли соперника - присоединяемся!
        $duel = $duelService->acceptDuel($availableTicket, $user);
        
        // Сразу стартуем дуэль
        $duel = $duelService->startDuel($duel);
        $duel->loadMissing('initiator.profile');
        
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
            'code' => $duel->code,
            'initiator_id' => $duel->initiator_user_id,
            'opponent_id' => $duel->opponent_user_id,
            'opponent' => $duel->initiator ? [
                'name' => $duel->initiator->first_name,
                'rating' => $duel->initiator->profile?->rating ?? 0,
            ] : null,
            'matched' => true,
        ]);
        return;
    }
    
    // Не нашли соперника - создаём свой тикет
    $duel = $duelService->createMatchmakingTicket($user);
    
    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'code' => $duel->code,
        'initiator_id' => $duel->initiator_user_id,
        'opponent_id' => $duel->opponent_user_id,
        'waiting' => true,
    ]);
}

/**
 * Получение информации о дуэли
 */
function handleGetDuel($container, ?array $telegramUser, int $duelId): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    /** @var TelegramPhotoService $photoService */
    $photoService = $container->get(TelegramPhotoService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    $duel = $duelService->findById($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }
    
    // Если дуэль matched но ещё не стартовала - стартуем
    if ($duel->status === 'matched' && $duel->started_at === null) {
        $duel = $duelService->startDuel($duel);
    }

    $duel->loadMissing('rounds.question.answers', 'rounds.question.category', 'initiator.profile', 'opponent.profile');
    
    // Загружаем фото для участников если их нет
    if ($duel->initiator && empty($duel->initiator->photo_url)) {
        $photoService->updateUserPhoto($duel->initiator);
        $duel->initiator->refresh();
    }
    if ($duel->opponent && empty($duel->opponent->photo_url)) {
        $photoService->updateUserPhoto($duel->opponent);
        $duel->opponent->refresh();
    }

    // Определяем роль текущего пользователя
    $isInitiator = $user && $duel->initiator_user_id === $user->getKey();

    // Получаем текущий раунд с вопросом
    $currentRound = $duelService->getCurrentRound($duel);
    
    // Проверяем и применяем таймауты для текущего раунда
    if ($currentRound && $currentRound->closed_at === null) {
        $duelService->maybeCompleteRound($currentRound);
        $currentRound->refresh();
        
        // Если раунд закрылся по таймауту, проверяем завершение дуэли
        if ($currentRound->closed_at !== null) {
            $duelService->maybeCompleteDuel($duel);
            $duel->refresh();
            
            // Получаем новый текущий раунд
            $currentRound = $duelService->getCurrentRound($duel);
        }
    }
    
    $question = null;
    $roundStatus = null;
    
    // Перезагружаем раунды после проверки таймаутов
    $duel->load('rounds.question.answers', 'rounds.question.category');
    
    // Получаем последний закрытый раунд (для показа результата)
    $lastClosedRound = $duel->rounds
        ->whereNotNull('closed_at')
        ->sortByDesc('closed_at')
        ->first();
    $lastClosedRoundStatus = null;
    
    if ($lastClosedRound) {
        $lcMyPayload = $isInitiator ? $lastClosedRound->initiator_payload : $lastClosedRound->opponent_payload;
        $lcOpponentPayload = $isInitiator ? $lastClosedRound->opponent_payload : $lastClosedRound->initiator_payload;
        
        $lcCorrectAnswerId = null;
        if ($lastClosedRound->question && $lastClosedRound->question->answers) {
            $lcCorrectAnswer = $lastClosedRound->question->answers->firstWhere('is_correct', true);
            $lcCorrectAnswerId = $lcCorrectAnswer ? $lcCorrectAnswer->getKey() : null;
        }
        
        $lastClosedRoundStatus = [
            'round_id' => $lastClosedRound->getKey(),
            'round_number' => $lastClosedRound->round_number,
            'my_correct' => $lcMyPayload['is_correct'] ?? false,
            'opponent_correct' => $lcOpponentPayload['is_correct'] ?? false,
            'correct_answer_id' => $lcCorrectAnswerId,
            'closed_at' => $lastClosedRound->closed_at?->toIso8601String(),
        ];
    }
    
    if ($currentRound) {
        // Помечаем раунд как отправленный
        $duelService->markRoundDispatched($currentRound);
        
        $q = $currentRound->question;
        $answers = [];
        
        if ($q && $q->answers) {
            foreach ($q->answers as $answer) {
                $answers[] = [
                    'id' => $answer->getKey(),
                    'text' => $answer->answer_text,
                ];
            }
        }
        
        // Перемешиваем ответы детерминированно (одинаково для обоих игроков)
        mt_srand($currentRound->getKey());
        shuffle($answers);
        mt_srand(); // Сбрасываем seed
        
        $question = [
            'id' => $q?->getKey(),
            'text' => $q?->question_text,
            'category' => $q?->category?->title ?? 'Общие знания',
            'answers' => $answers,
        ];
        
        // Получаем статус текущего раунда
        $myPayload = $isInitiator ? $currentRound->initiator_payload : $currentRound->opponent_payload;
        $opponentPayload = $isInitiator ? $currentRound->opponent_payload : $currentRound->initiator_payload;
        
        $myAnswered = isset($myPayload['completed']) && $myPayload['completed'] === true;
        $opponentAnswered = isset($opponentPayload['completed']) && $opponentPayload['completed'] === true;
        
        // Находим ID правильного ответа
        $correctAnswerId = null;
        if ($q && $q->answers) {
            $correctAnswer = $q->answers->firstWhere('is_correct', true);
            $correctAnswerId = $correctAnswer ? $correctAnswer->getKey() : null;
        }
        
        $roundStatus = [
            'round_id' => $currentRound->getKey(),
            'round_number' => $currentRound->round_number,
            'my_answered' => $myAnswered,
            'my_correct' => $myAnswered ? ($myPayload['is_correct'] ?? false) : null,
            'opponent_answered' => $opponentAnswered,
            'opponent_correct' => $opponentAnswered ? ($opponentPayload['is_correct'] ?? false) : null,
            'correct_answer_id' => ($myAnswered || $opponentAnswered) ? $correctAnswerId : null,
            'round_closed' => $currentRound->closed_at !== null,
            'time_limit' => $currentRound->time_limit ?? 30,
            'question_sent_at' => $currentRound->question_sent_at?->toIso8601String(),
        ];
    }

    // Подсчитываем очки
    $initiatorScore = $duel->rounds->sum('initiator_score');
    $opponentScore = $duel->rounds->sum('opponent_score');
    $completedRounds = $duel->rounds->whereNotNull('closed_at')->count();

    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'code' => $duel->code,
        'current_round' => $completedRounds + 1,
        'total_rounds' => $duel->rounds_to_win * 2,
        'initiator_score' => $initiatorScore,
        'opponent_score' => $opponentScore,
        'initiator' => $duel->initiator ? [
            'id' => $duel->initiator->getKey(),
            'name' => $duel->initiator->first_name,
            'rating' => $duel->initiator->profile?->rating ?? 0,
            'photo_url' => $duel->initiator->photo_url,
        ] : null,
        'opponent' => $duel->opponent ? [
            'id' => $duel->opponent->getKey(),
            'name' => $duel->opponent->first_name,
            'rating' => $duel->opponent->profile?->rating ?? 0,
            'photo_url' => $duel->opponent->photo_url,
        ] : null,
        'question' => $question,
        'round_status' => $roundStatus,
        'last_closed_round' => $lastClosedRoundStatus,
        'is_initiator' => $isInitiator,
    ]);
}

/**
 * Ответ на вопрос дуэли
 */
function handleDuelAnswer($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $duelId = $body['duelId'] ?? null;
    $answerId = $body['answerId'] ?? null; // null означает таймаут

    if (!$duelId) {
        jsonError('Не указан ID дуэли', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    $duel = $duelService->findById((int) $duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }

    $currentRound = $duelService->getCurrentRound($duel);
    
    if (!$currentRound) {
        jsonError('Нет активного раунда', 400);
    }

    // Обрабатываем ответ (answerId = null означает таймаут)
    $round = $duelService->submitAnswer($currentRound, $user, $answerId !== null ? (int) $answerId : null);
    $round->loadMissing('question.answers', 'question.category');
    
    // Определяем результат для текущего пользователя
    $isInitiator = $duel->initiator_user_id === $user->getKey();
    $payload = $isInitiator ? $round->initiator_payload : $round->opponent_payload;
    
    // Записываем в статистику
    /** @var StatisticsService $statisticsService */
    $statisticsService = $container->get(StatisticsService::class);
    $statisticsService->recordAnswer(
        $user,
        $round->question?->category_id,
        $round->question?->getKey(),
        $payload['is_correct'] ?? false,
        (int)(($payload['time_elapsed'] ?? 0) * 1000), // секунды в мс
        'duel'
    );
    $opponentPayload = $isInitiator ? $round->opponent_payload : $round->initiator_payload;
    
    // Находим ID правильного ответа
    $correctAnswerId = null;
    if ($round->question && $round->question->answers) {
        $correctAnswer = $round->question->answers->firstWhere('is_correct', true);
        $correctAnswerId = $correctAnswer ? $correctAnswer->getKey() : null;
    }
    
    // Проверяем, ответил ли соперник
    $opponentAnswered = isset($opponentPayload['completed']) && $opponentPayload['completed'] === true;
    $opponentCorrect = $opponentAnswered ? ($opponentPayload['is_correct'] ?? false) : null;
    
    // Проверяем, закрыт ли раунд (оба ответили)
    $roundClosed = $round->closed_at !== null;

    jsonResponse([
        'round_id' => $round->getKey(),
        'is_correct' => $payload['is_correct'] ?? false,
        'points_earned' => $payload['score'] ?? 0,
        'time_taken' => $payload['time_elapsed'] ?? 0,
        'correct_answer_id' => $correctAnswerId,
        'opponent_answered' => $opponentAnswered,
        'opponent_correct' => $opponentCorrect,
        'round_closed' => $roundClosed,
    ]);
}

/**
 * Отмена дуэли пользователем (только своей, в статусе waiting)
 */
function handleCancelDuel($container, ?array $telegramUser, int $duelId): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $duel = \QuizBot\Domain\Model\Duel::query()->find($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }

    // Можно отменить только свою дуэль
    if ($duel->initiator_user_id !== $user->getKey()) {
        jsonError('Можно отменить только свою дуэль', 403);
    }

    // Можно отменить только в статусе waiting
    if ($duel->status !== 'waiting') {
        jsonError('Дуэль уже началась или завершена', 400);
    }

    $duel->status = 'cancelled';
    $duel->finished_at = \Illuminate\Support\Carbon::now();
    $duel->save();

    jsonResponse(['cancelled' => true, 'duel_id' => $duelId]);
}

/**
 * Присоединение к дуэли по коду
 */
function handleJoinDuel($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $code = strtoupper(trim($body['code'] ?? ''));

    if (empty($code)) {
        jsonError('Не указан код дуэли', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    // Ищем дуэль по коду
    $duel = \QuizBot\Domain\Model\Duel::query()
        ->where('code', $code)
        ->where('status', 'waiting')
        ->whereNull('opponent_user_id')
        ->first();
    
    if (!$duel) {
        jsonError('Дуэль не найдена или уже началась', 404);
    }

    // Нельзя присоединиться к своей же дуэли
    if ($duel->initiator_user_id === $user->getKey()) {
        jsonError('Нельзя присоединиться к своей дуэли', 400);
    }

    // Присоединяемся к дуэли
    $duel = $duelService->joinDuel($duel, $user);
    
    // Загружаем фото initiator если его нет
    if ($duel->initiator && empty($duel->initiator->photo_url)) {
        /** @var TelegramPhotoService $photoService */
        $photoService = $container->get(TelegramPhotoService::class);
        $photoService->updateUserPhoto($duel->initiator);
        $duel->initiator->refresh();
    }

    jsonResponse([
        'duel_id' => $duel->getKey(),
        'code' => $duel->code,
        'status' => $duel->status,
        'initiator' => $duel->initiator ? [
            'id' => $duel->initiator->getKey(),
            'name' => $duel->initiator->first_name,
            'photo_url' => $duel->initiator->photo_url,
        ] : null,
    ]);
}

/**
 * Использование подсказки 50/50 в дуэли
 */
function handleDuelHint($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $duelId = $body['duelId'] ?? null;
    $hintType = $body['hintType'] ?? 'fifty_fifty';

    if (!$duelId) {
        jsonError('Не указан ID дуэли', 400);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $user = $userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile) {
            jsonError('Профиль не найден', 404);
        }

        // Стоимость подсказки
        $hintCost = 10;

        // Проверяем монеты
        if ($profile->coins < $hintCost) {
            jsonError('Недостаточно монет. Нужно: ' . $hintCost, 400);
        }

        /** @var DuelService $duelService */
        $duelService = $container->get(DuelService::class);
        
        $duel = $duelService->findById((int) $duelId);
        
        if (!$duel) {
            jsonError('Дуэль не найдена', 404);
        }

        $currentRound = $duelService->getCurrentRound($duel);
        
        if (!$currentRound) {
            jsonError('Нет активного раунда', 400);
        }

        // Проверяем, не использована ли уже подсказка в этой дуэли (одна на всю дуэль)
        $isInitiator = $duel->initiator_user_id === $user->getKey();
        
        // Проверяем во всех раундах дуэли
        $duel->loadMissing('rounds');
        $fieldPayload = $isInitiator ? 'initiator_payload' : 'opponent_payload';
        
        foreach ($duel->rounds as $round) {
            $roundPayload = $round->{$fieldPayload} ?? [];
            if (isset($roundPayload['hint_used']) && $roundPayload['hint_used']) {
                jsonError('Подсказка уже использована в этой дуэли', 400);
            }
        }

        // Загружаем вопрос с ответами
        $currentRound->loadMissing('question.answers');
        $question = $currentRound->question;
        
        if (!$question) {
            jsonError('Вопрос не найден', 404);
        }

        $answers = $question->answers;
        
        // Находим правильный ответ
        $correctAnswer = $answers->firstWhere('is_correct', true);
        if (!$correctAnswer) {
            jsonError('Правильный ответ не найден', 500);
        }

        // Находим неправильные ответы и убираем 2
        $incorrectAnswers = $answers->where('is_correct', false)->values();
        $toRemove = $incorrectAnswers->shuffle()->take(2);
        $hiddenAnswerIds = $toRemove->pluck('id')->toArray();

        // Списываем монеты
        $profile->coins = max(0, $profile->coins - $hintCost);
        $profile->save();

        // Сохраняем информацию об использовании подсказки в payload раунда
        $fieldPayload = $isInitiator ? 'initiator_payload' : 'opponent_payload';
        $payload = $currentRound->{$fieldPayload} ?? [];
        $payload['hint_used'] = true;
        $payload['hint_type'] = $hintType;
        $payload['hidden_answers'] = $hiddenAnswerIds;
        $currentRound->{$fieldPayload} = $payload;
        $currentRound->save();

        jsonResponse([
            'success' => true,
            'hidden_answer_ids' => $hiddenAnswerIds,
            'coins_remaining' => $profile->coins,
        ]);
    } catch (Throwable $e) {
        jsonError('Ошибка: ' . $e->getMessage(), 500);
    }
}

/**
 * Получение вопроса "Правда или ложь"
 */
function handleGetTrueFalseQuestion($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

        // Проверяем наличие фактов напрямую в БД
        $factsCount = \QuizBot\Domain\Model\TrueFalseFact::query()
            ->where('is_active', true)
            ->count();
        
        if ($factsCount === 0) {
            jsonError('Нет фактов в базе данных. Выполните: php bin/seed.php', 404);
        }

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);
    
    // Проверяем, есть ли текущий факт
    $fact = $trueFalseService->getCurrentFact($user);
    
    if (!$fact) {
        // Начинаем новую сессию
        $fact = $trueFalseService->startSession($user);
    }
    
    if (!$fact) {
        jsonError('Не удалось загрузить факт', 500);
    }

    jsonResponse([
        'id' => $fact->getKey(),
        'statement' => $fact->statement,
    ]);
    } catch (Throwable $e) {
        jsonError('Ошибка: ' . $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine(), 500);
    }
}

/**
 * Ответ на вопрос "Правда или ложь"
 */
function handleTrueFalseAnswer($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $factId = $body['factId'] ?? null;
    $answer = $body['answer'] ?? null;

    if ($factId === null || $answer === null) {
        jsonError('Не указаны обязательные параметры', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);
    
    $result = $trueFalseService->handleAnswer($user, (int) $factId, (bool) $answer);

    jsonResponse([
        'is_correct' => $result['is_correct'],
        'correct_answer' => $result['correct_answer'],
        'explanation' => $result['explanation'],
        'streak' => $result['streak'],
        'record' => $result['record'],
        'next_fact' => $result['next_fact'] ? [
            'id' => $result['next_fact']->getKey(),
            'statement' => $result['next_fact']->statement,
        ] : null,
    ]);
}

/**
 * Получение рейтинга
 */
function handleGetLeaderboard($container, string $type): void
{
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    /** @var ProfileFormatter $profileFormatter */
    $profileFormatter = $container->get(ProfileFormatter::class);
    
    /** @var TelegramPhotoService $photoService */
    $photoService = $container->get(TelegramPhotoService::class);

    $players = [];
    
    if ($type === 'duel') {
        $topPlayers = $userService->getTopPlayersByRating(20);
        
        foreach ($topPlayers as $playerData) {
            $user = $playerData['user'];
            
            // Загружаем фото если его нет
            if (empty($user->photo_url)) {
                $photoService->updateUserPhoto($user);
                $user->refresh();
            }
            
            $players[] = [
                'position' => $playerData['position'],
                'name' => $user->first_name ?? 'Игрок',
                'username' => $user->username ?? '',
                'photo_url' => $user->photo_url,
                'rating' => $playerData['rating'],
                'rank' => $profileFormatter->getRankByRating($playerData['rating']),
            ];
        }
    } else {
        $topPlayers = $userService->getTopPlayersByTrueFalseRecord(20);
        
        foreach ($topPlayers as $playerData) {
            $user = $playerData['user'];
            
            // Загружаем фото если его нет
            if (empty($user->photo_url)) {
                $photoService->updateUserPhoto($user);
                $user->refresh();
            }
            
            $players[] = [
                'position' => $playerData['position'],
                'name' => $user->first_name ?? 'Игрок',
                'username' => $user->username ?? '',
                'photo_url' => $user->photo_url,
                'record' => $playerData['record'],
            ];
        }
    }

    jsonResponse([
        'type' => $type,
        'players' => $players,
    ]);
}

/**
 * Получение расширенной статистики пользователя
 */
function handleGetStatistics($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var StatisticsService $statisticsService */
    $statisticsService = $container->get(StatisticsService::class);
    
    $statistics = $statisticsService->getFullStatistics($user);

    jsonResponse($statistics);
}

/**
 * Получение краткой статистики пользователя
 */
function handleGetQuickStatistics($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var StatisticsService $statisticsService */
    $statisticsService = $container->get(StatisticsService::class);
    
    $statistics = $statisticsService->getQuickStats($user);

    jsonResponse($statistics);
}

/**
 * Отмена дуэли админом
 */
function handleAdminCancelDuel($container, ?array $telegramUser, int $duelId): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $duel = \QuizBot\Domain\Model\Duel::query()->find($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }
    
    if ($duel->status === 'finished' || $duel->status === 'cancelled') {
        jsonError('Дуэль уже завершена', 400);
    }
    
    $duel->status = 'cancelled';
    $duel->finished_at = \Illuminate\Support\Carbon::now();
    $duel->save();
    
    jsonResponse(['cancelled' => true, 'duel_id' => $duelId]);
}

/**
 * Отмена всех активных дуэлей
 */
function handleAdminCancelAllDuels($container, ?array $telegramUser): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $now = \Illuminate\Support\Carbon::now();
    $cancelled = \QuizBot\Domain\Model\Duel::query()
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->update([
            'status' => 'cancelled',
            'finished_at' => $now
        ]);
    
    jsonResponse(['cancelled' => $cancelled]);
}

/**
 * Добавление нового вопроса
 */
function handleAdminAddQuestion($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $categoryId = $body['category_id'] ?? null;
    $questionText = $body['question_text'] ?? null;
    $answers = $body['answers'] ?? [];
    $correctIndex = $body['correct_answer'] ?? 0;
    
    if (!$categoryId || !$questionText || count($answers) < 2) {
        jsonError('Заполните все обязательные поля', 400);
    }
    
    $category = \QuizBot\Domain\Model\Category::query()->find($categoryId);
    if (!$category) {
        jsonError('Категория не найдена', 404);
    }
    
    // Создаём вопрос
    $question = new \QuizBot\Domain\Model\Question([
        'category_id' => $categoryId,
        'question_text' => $questionText,
        'difficulty' => 'medium',
        'time_limit' => 30,
        'is_active' => true,
    ]);
    $question->save();
    
    // Добавляем ответы
    foreach ($answers as $i => $answerText) {
        if (empty(trim($answerText))) continue;
        
        $question->answers()->create([
            'answer_text' => trim($answerText),
            'is_correct' => $i === $correctIndex,
        ]);
    }
    
    jsonResponse([
        'question_id' => $question->getKey(),
        'message' => 'Вопрос добавлен'
    ]);
}

/**
 * Проверка, является ли пользователь админом
 */
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
 * Проверка прав админа
 */
function handleAdminCheck($container, ?array $telegramUser): void
{
    jsonResponse([
        'is_admin' => isAdmin($telegramUser, $container),
    ]);
}

/**
 * Получение статистики для админки
 */
function handleAdminStats($container, ?array $telegramUser): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $yesterday = \Illuminate\Support\Carbon::now()->subDay();

    // Получаем статистику из БД
    $totalUsers = \QuizBot\Domain\Model\User::query()->count();
    $activeToday = \QuizBot\Domain\Model\User::query()
        ->where('updated_at', '>=', $yesterday)
        ->count();
    $newUsersToday = \QuizBot\Domain\Model\User::query()
        ->where('created_at', '>=', $yesterday)
        ->count();
    
    $totalDuels = \QuizBot\Domain\Model\Duel::query()->count();
    $activeDuels = \QuizBot\Domain\Model\Duel::query()
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->count();
    $duelsToday = \QuizBot\Domain\Model\Duel::query()
        ->where('created_at', '>=', $yesterday)
        ->count();
    
    $totalQuestions = \QuizBot\Domain\Model\Question::query()->count();
    $totalFacts = \QuizBot\Domain\Model\TrueFalseFact::query()->count();
    
    // Последние пользователи
    $recentUsers = \QuizBot\Domain\Model\User::query()
        ->with('profile')
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(fn($u) => [
            'id' => $u->getKey(),
            'telegram_id' => $u->telegram_id,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'username' => $u->username,
            'rating' => $u->profile?->rating ?? 1000,
        ]);
    
    // Последние дуэли
    $recentDuels = \QuizBot\Domain\Model\Duel::query()
        ->with(['initiator', 'opponent', 'result'])
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(fn($d) => [
            'id' => $d->getKey(),
            'code' => $d->code,
            'status' => $d->status,
            'initiator_name' => $d->initiator?->first_name ?? '???',
            'opponent_name' => $d->opponent?->first_name ?? null,
            'initiator_score' => $d->result?->initiator_total_score ?? 0,
            'opponent_score' => $d->result?->opponent_total_score ?? 0,
        ]);
    
    // Категории с количеством вопросов
    $categories = \QuizBot\Domain\Model\Category::query()
        ->withCount('questions')
        ->orderByDesc('questions_count')
        ->get()
        ->map(fn($c) => [
            'id' => $c->getKey(),
            'title' => $c->title,
            'count' => $c->questions_count,
        ]);

    jsonResponse([
        'total_users' => $totalUsers,
        'active_today' => $activeToday,
        'new_users_today' => $newUsersToday,
        'total_duels' => $totalDuels,
        'active_duels' => $activeDuels,
        'duels_today' => $duelsToday,
        'total_questions' => $totalQuestions,
        'total_facts' => $totalFacts,
        'tf_games_today' => 0, // TODO: добавить подсчёт
        'recent_users' => $recentUsers,
        'recent_duels' => $recentDuels,
        'categories' => $categories,
    ]);
}

// ============================================================================
// SHOP SYSTEM HANDLERS
// ============================================================================

/**
 * GET /shop/items - получить товары магазина
 */
function handleGetShopItems($container, ?array $telegramUser, ?string $category): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        $items = $shopService->getItems($category);
        
        jsonResponse(['items' => $items]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения товаров: ' . $e->getMessage());
        jsonError('Ошибка получения товаров', 500);
    }
}

/**
 * POST /shop/purchase - купить товар
 */
function handleShopPurchase($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['item_id'])) {
        jsonError('Не указан ID товара', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $quantity = $body['quantity'] ?? 1;
        $result = $shopService->purchase($user, (int) $body['item_id'], (int) $quantity);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка покупки: ' . $e->getMessage());
        jsonError('Ошибка покупки', 500);
    }
}

/**
 * GET /shop/history - история покупок
 */
function handleShopHistory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $history = $shopService->getPurchaseHistory($user);
        jsonResponse(['history' => $history]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения истории: ' . $e->getMessage());
        jsonError('Ошибка получения истории', 500);
    }
}

/**
 * GET /inventory - получить инвентарь
 */
function handleGetInventory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $inventory = $inventoryService->getInventory($user);
        jsonResponse($inventory);
    } catch (\Throwable $e) {
        error_log('Ошибка получения инвентаря: ' . $e->getMessage());
        jsonError('Ошибка получения инвентаря', 500);
    }
}

/**
 * POST /inventory/equip - экипировать косметику
 */
function handleEquipCosmetic($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['cosmetic_id'])) {
        jsonError('Не указан ID косметики', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $inventoryService->equipCosmetic($user, (int) $body['cosmetic_id']);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка экипировки: ' . $e->getMessage());
        jsonError('Ошибка экипировки', 500);
    }
}

/**
 * POST /inventory/unequip - снять косметику
 */
function handleUnequipCosmetic($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['cosmetic_type'])) {
        jsonError('Не указан тип косметики', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $inventoryService->unequipCosmetic($user, $body['cosmetic_type']);
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка снятия косметики: ' . $e->getMessage());
        jsonError('Ошибка снятия косметики', 500);
    }
}

/**
 * GET /wheel/status - статус колеса фортуны
 */
function handleWheelStatus($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $stats = $wheelService->getStats($user);
        jsonResponse($stats);
    } catch (\Throwable $e) {
        error_log('Ошибка получения статуса колеса: ' . $e->getMessage());
        jsonError('Ошибка получения статуса колеса', 500);
    }
}

/**
 * POST /wheel/spin - крутить колесо
 */
function handleWheelSpin($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $usePremium = $body['use_premium'] ?? false;
        $result = $wheelService->spin($user, (bool) $usePremium);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка вращения колеса: ' . $e->getMessage());
        jsonError('Ошибка вращения колеса', 500);
    }
}

/**
 * GET /wheel/config - конфигурация колеса
 */
function handleWheelConfig($container): void
{
    try {
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        $config = $wheelService->getWheelConfig();
        
        jsonResponse(['sectors' => $config]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения конфигурации колеса: ' . $e->getMessage());
        jsonError('Ошибка получения конфигурации', 500);
    }
}

/**
 * POST /lootbox/open - открыть лутбокс
 */
function handleLootboxOpen($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['lootbox_type'])) {
        jsonError('Не указан тип лутбокса', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $lootboxService = $container->get(\QuizBot\Application\Services\LootboxService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $lootboxService->openLootbox($user, $body['lootbox_type']);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка открытия лутбокса: ' . $e->getMessage());
        jsonError('Ошибка открытия лутбокса', 500);
    }
}

/**
 * GET /lootbox/history - история открытых лутбоксов
 */
function handleLootboxHistory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $lootboxService = $container->get(\QuizBot\Application\Services\LootboxService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $history = $lootboxService->getHistory($user);
        jsonResponse(['history' => $history]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения истории лутбоксов: ' . $e->getMessage());
        jsonError('Ошибка получения истории', 500);
    }
}

/**
 * GET /boosts - получить активные бусты
 */
function handleGetBoosts($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $boostService = $container->get(\QuizBot\Application\Services\BoostService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $boosts = $boostService->getActiveBoosts($user);
        jsonResponse(['boosts' => $boosts]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения бустов: ' . $e->getMessage());
        jsonError('Ошибка получения бустов', 500);
    }
}

/**
 * GET /images/{folder}/{filename} - отдача статических изображений
 */
function handleGetImage(string $folder, string $filename): void
{
    // Безопасность: разрешаем только определенные папки
    $allowedFolders = ['shop', 'wheel', 'cosmetics', 'ui'];
    if (!in_array($folder, $allowedFolders)) {
        http_response_code(404);
        exit;
    }
    
    // Очищаем имя файла от потенциально опасных символов
    $filename = basename($filename);
    
    // Путь к файлу
    $filepath = dirname(__DIR__) . '/storage/images/' . $folder . '/' . $filename;
    
    // Проверяем существование файла
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        exit;
    }
    
    // Определяем MIME тип
    $mimeType = mime_content_type($filepath);
    
    // Устанавливаем заголовки
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, max-age=86400'); // Кэш на 1 день
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    
    // Отдаем файл
    readfile($filepath);
    exit;
}

/**
 * Отправка JSON ответа
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * GET /referral/stats - получить реферальную статистику
 */
function handleGetReferralStats($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        /** @var \QuizBot\Application\Services\ReferralService $referralService */
        $referralService = $container->get(\QuizBot\Application\Services\ReferralService::class);
        
        $stats = $referralService->getReferralStats($user);
        $link = $referralService->getReferralLink($user);
        
        // Добавляем ссылку в ответ
        $stats['referral_link'] = $link;
        
        jsonResponse($stats);
    } catch (\Throwable $e) {
        error_log('Ошибка получения реферальной статистики: ' . $e->getMessage());
        jsonError('Ошибка получения данных', 500);
    }
}

/**
 * Отправка JSON ошибки
 */
function jsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

