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
        
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
            'code' => $duel->code,
            'initiator_id' => $duel->initiator_user_id,
            'opponent_id' => $duel->opponent_user_id,
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
    
    $duel = $duelService->findById($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }
    
    // Если дуэль matched но ещё не стартовала - стартуем
    if ($duel->status === 'matched' && $duel->started_at === null) {
        $duel = $duelService->startDuel($duel);
    }

    $duel->loadMissing('rounds.question.answers', 'rounds.question.category', 'initiator', 'opponent');

    // Получаем текущий раунд с вопросом
    $currentRound = $duelService->getCurrentRound($duel);
    $question = null;
    
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
        'code' => $duel->code,
        'current_round' => $completedRounds + 1,
        'total_rounds' => $duel->rounds_to_win * 2,
        'initiator_score' => $initiatorScore,
        'opponent_score' => $opponentScore,
        'initiator' => $duel->initiator ? [
            'id' => $duel->initiator->getKey(),
            'name' => $duel->initiator->first_name,
        ] : null,
        'opponent' => $duel->opponent ? [
            'id' => $duel->opponent->getKey(),
            'name' => $duel->opponent->first_name,
        ] : null,
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

