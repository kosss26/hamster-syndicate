<?php

declare(strict_types=1);

use QuizBot\Application\Services\AchievementTrackerService;
use QuizBot\Application\Services\CollectionService;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\UserService;

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

    if ($factId === null) {
        jsonError('Не указан факт', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $fact = \QuizBot\Domain\Model\TrueFalseFact::query()
        ->where('is_active', true)
        ->find((int) $factId);

    if (!$fact) {
        jsonError('Факт не найден', 404);
    }

    $isTimeout = $answer === null;
    $answerBool = $isTimeout
        ? !(bool) $fact->is_true
        : (bool) $answer;

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);
    
    $result = $trueFalseService->handleAnswer($user, (int) $factId, $answerBool);
    $xpGain = $result['is_correct'] ? (6 + min(10, (int) $result['streak'])) : 2;
    $xpResult = $userService->grantExperience($user, $xpGain);

    /** @var AchievementTrackerService $achievementTracker */
    $achievementTracker = $container->get(AchievementTrackerService::class);
    if ($result['is_correct']) {
        $achievementTracker->incrementStat($user->getKey(), 'true_false_correct');
    }
    $achievementUnlocks = $achievementTracker->checkAndUnlock($user->getKey(), [
        'context' => 'truefalse_answer',
        'is_correct' => (bool) $result['is_correct'],
        'is_timeout' => $isTimeout,
        'streak' => (int) ($result['streak'] ?? 0),
    ]);

    /** @var CollectionService $collectionService */
    $collectionService = $container->get(CollectionService::class);
    $collectionDrop = $collectionService->awardDropForEvent($user->getKey(), 'truefalse', [
        'is_success' => (bool) $result['is_correct'],
        'is_timeout' => $isTimeout,
        'streak' => (int) ($result['streak'] ?? 0),
    ]);

    jsonResponse([
        'is_correct' => $result['is_correct'],
        'correct_answer' => $result['correct_answer'],
        'explanation' => $result['explanation'],
        'streak' => $result['streak'],
        'record' => $result['record'],
        'experience' => $xpResult,
        'achievement_unlocks' => $achievementUnlocks,
        'collection_drops' => $collectionDrop ? [$collectionDrop] : [],
        'next_fact' => $result['next_fact'] ? [
            'id' => $result['next_fact']->getKey(),
            'statement' => $result['next_fact']->statement,
        ] : null,
    ]);
}
