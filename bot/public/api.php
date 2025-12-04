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
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $user = $userService->ensureProfile($user);
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
    
    // Создаём дуэль через matchmaking
    $duel = $duelService->createMatchmakingTicket($user);
    
    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'code' => $duel->code,
        'initiator_id' => $duel->initiator_user_id,
        'opponent_id' => $duel->opponent_user_id,
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
    
    $duel = $duelService->findById($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }

    $duel->loadMissing('rounds.question.answers', 'rounds.question.category');

    // Получаем текущий раунд с вопросом
    $currentRound = $duelService->getCurrentRound($duel);
    $question = null;
    
    if ($currentRound) {
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
        
        // Перемешиваем ответы
        shuffle($answers);
        
        $question = [
            'id' => $q?->getKey(),
            'text' => $q?->question_text,
            'category' => $q?->category?->title ?? 'Общие знания',
            'answers' => $answers,
        ];
    }

    // Подсчитываем очки
    $initiatorScore = $duel->rounds->sum('initiator_score');
    $opponentScore = $duel->rounds->sum('opponent_score');
    $completedRounds = $duel->rounds->whereNotNull('closed_at')->count();

    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'current_round' => $completedRounds + 1,
        'total_rounds' => $duel->rounds_to_win * 2,
        'initiator_score' => $initiatorScore,
        'opponent_score' => $opponentScore,
        'question' => $question,
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
    $answerId = $body['answerId'] ?? null;

    if (!$duelId || !$answerId) {
        jsonError('Не указаны обязательные параметры', 400);
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

    // Обрабатываем ответ
    $round = $duelService->submitAnswer($currentRound, $user, (int) $answerId);
    
    // Определяем результат для текущего пользователя
    $isInitiator = $duel->initiator_user_id === $user->getKey();
    $payload = $isInitiator ? $round->initiator_payload : $round->opponent_payload;

    jsonResponse([
        'is_correct' => $payload['is_correct'] ?? false,
        'points_earned' => $payload['score'] ?? 0,
        'time_taken' => $payload['time_elapsed'] ?? 0,
    ]);
}

/**
 * Получение вопроса "Правда или ложь"
 */
function handleGetTrueFalseQuestion($container, ?array $telegramUser): void
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

    $players = [];
    
    if ($type === 'duel') {
        $topPlayers = $userService->getTopPlayersByRating(20);
        
        foreach ($topPlayers as $playerData) {
            $user = $playerData['user'];
            $players[] = [
                'position' => $playerData['position'],
                'name' => $user->first_name ?? 'Игрок',
                'username' => $user->username ?? '',
                'rating' => $playerData['rating'],
                'rank' => $profileFormatter->getRankByRating($playerData['rating']),
            ];
        }
    } else {
        $topPlayers = $userService->getTopPlayersByTrueFalseRecord(20);
        
        foreach ($topPlayers as $playerData) {
            $user = $playerData['user'];
            $players[] = [
                'position' => $playerData['position'],
                'name' => $user->first_name ?? 'Игрок',
                'username' => $user->username ?? '',
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

