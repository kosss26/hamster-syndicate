<?php

declare(strict_types=1);

/**
 * API для Telegram Mini App
 * 
 * Этот файл обрабатывает HTTP-запросы от Mini App и взаимодействует
 * с существующими сервисами бота.
 */

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Repository\UserRepositoryInterface;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\TrueFalseService;

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
    new AppBootstrap(dirname(__DIR__));
    
    $containerBuilder = new ContainerBuilder();
    $containerBuilder->addDefinitions(dirname(__DIR__) . '/config/di.php');
    $container = $containerBuilder->build();
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
$telegramUser = verifyTelegramInitData($initData);

// Роутинг
try {
    switch (true) {
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

        default:
            jsonError('Маршрут не найден', 404);
    }
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Верификация initData из Telegram
 */
function verifyTelegramInitData(string $initData): ?array
{
    if (empty($initData)) {
        // Для разработки возвращаем тестового пользователя
        if (getenv('APP_ENV') === 'development') {
            return [
                'id' => 123456789,
                'first_name' => 'Тестовый',
                'last_name' => 'Пользователь',
                'username' => 'test_user'
            ];
        }
        return null;
    }

    // Парсим initData
    parse_str($initData, $data);
    
    if (!isset($data['hash']) || !isset($data['user'])) {
        return null;
    }

    // Верификация подписи
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $checkHash = $data['hash'];
    unset($data['hash']);

    ksort($data);
    $dataCheckString = implode("\n", array_map(
        fn($key, $value) => "$key=$value",
        array_keys($data),
        array_values($data)
    ));

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if ($hash !== $checkHash) {
        return null;
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

    /** @var UserRepositoryInterface $userRepo */
    $userRepo = $container->get(UserRepositoryInterface::class);
    
    $user = $userRepo->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    jsonResponse([
        'id' => $user->getId(),
        'telegram_id' => $user->getTelegramId(),
        'username' => $user->getUsername(),
        'first_name' => $user->getFirstName(),
        'last_name' => $user->getLastName(),
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
    
    /** @var UserRepositoryInterface $userRepo */
    $userRepo = $container->get(UserRepositoryInterface::class);
    
    $user = $userRepo->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $profile = $userService->getProfileByUserId($user->getId());
    
    if (!$profile) {
        jsonError('Профиль не найден', 404);
    }

    $rank = $profileFormatter->getRankByRating($profile->getRating());

    jsonResponse([
        'rating' => $profile->getRating(),
        'rank' => $rank,
        'coins' => $profile->getCoins(),
        'win_streak' => $profile->getWinStreak(),
        'true_false_record' => $profile->getTrueFalseRecord(),
        'stats' => [
            'duel_wins' => $profile->getDuelWins(),
            'duel_losses' => $profile->getDuelLosses(),
            'duel_draws' => $profile->getDuelDraws(),
            'total_games' => $profile->getDuelWins() + $profile->getDuelLosses() + $profile->getDuelDraws(),
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

    /** @var UserRepositoryInterface $userRepo */
    $userRepo = $container->get(UserRepositoryInterface::class);
    
    $user = $userRepo->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    $mode = $body['mode'] ?? 'random';
    
    // Создаём или находим дуэль
    $duel = $duelService->createOrJoinDuel($user);
    
    jsonResponse([
        'duel_id' => $duel->getId(),
        'status' => $duel->getStatus(),
        'challenger_id' => $duel->getChallengerId(),
        'opponent_id' => $duel->getOpponentId(),
        'current_round' => $duel->getCurrentRound(),
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

    // Получаем текущий раунд с вопросом
    $currentRound = $duelService->getCurrentRound($duel);
    $question = null;
    
    if ($currentRound) {
        $q = $currentRound->getQuestion();
        $answers = [];
        
        foreach ($q->getAnswers() as $answer) {
            $answers[] = [
                'id' => $answer->getId(),
                'text' => $answer->getText(),
            ];
        }
        
        // Перемешиваем ответы
        shuffle($answers);
        
        $question = [
            'id' => $q->getId(),
            'text' => $q->getText(),
            'category' => $q->getCategory()?->getName() ?? 'Общие знания',
            'answers' => $answers,
        ];
    }

    jsonResponse([
        'duel_id' => $duel->getId(),
        'status' => $duel->getStatus(),
        'current_round' => $duel->getCurrentRound(),
        'total_rounds' => 10,
        'challenger_score' => $duel->getChallengerScore(),
        'opponent_score' => $duel->getOpponentScore(),
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
    $roundId = $body['roundId'] ?? null;
    $answerId = $body['answerId'] ?? null;

    if (!$duelId || !$answerId) {
        jsonError('Не указаны обязательные параметры', 400);
    }

    /** @var UserRepositoryInterface $userRepo */
    $userRepo = $container->get(UserRepositoryInterface::class);
    
    $user = $userRepo->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    
    // Обрабатываем ответ
    $result = $duelService->processAnswer($user, (int) $duelId, (int) $answerId);

    jsonResponse([
        'is_correct' => $result['is_correct'],
        'correct_answer_id' => $result['correct_answer_id'],
        'points_earned' => $result['points_earned'] ?? 0,
        'time_taken' => $result['time_taken'] ?? 0,
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

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);
    
    $fact = $trueFalseService->getRandomFact();
    
    if (!$fact) {
        jsonError('Не удалось загрузить факт', 500);
    }

    jsonResponse([
        'id' => $fact->getId(),
        'statement' => $fact->getStatement(),
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

    /** @var UserRepositoryInterface $userRepo */
    $userRepo = $container->get(UserRepositoryInterface::class);
    
    $user = $userRepo->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);
    
    $result = $trueFalseService->checkAnswer($user, (int) $factId, (bool) $answer);

    jsonResponse([
        'is_correct' => $result['is_correct'],
        'correct_answer' => $result['correct_answer'],
        'explanation' => $result['explanation'],
        'streak' => $result['streak'],
        'record' => $result['record'],
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
        
        foreach ($topPlayers as $index => $player) {
            $players[] = [
                'position' => $index + 1,
                'name' => $player['first_name'] ?? 'Игрок',
                'username' => $player['username'] ?? '',
                'rating' => $player['rating'],
                'rank' => $profileFormatter->getRankByRating($player['rating']),
            ];
        }
    } else {
        $topPlayers = $userService->getTopPlayersByTrueFalseRecord(20);
        
        foreach ($topPlayers as $index => $player) {
            $players[] = [
                'position' => $index + 1,
                'name' => $player['first_name'] ?? 'Игрок',
                'username' => $player['username'] ?? '',
                'record' => $player['true_false_record'],
            ];
        }
    }

    jsonResponse([
        'type' => $type,
        'players' => $players,
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

