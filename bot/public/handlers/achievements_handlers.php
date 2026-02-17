<?php

declare(strict_types=1);

use QuizBot\Application\Services\AchievementService;
use QuizBot\Application\Services\CollectionService;
use QuizBot\Application\Services\UserService;

/**
 * GET /achievements - все достижения
 */
function handleGetAchievements($container): void
{
    try {
        /** @var AchievementService $achievementService */
        $achievementService = $container->get(AchievementService::class);
        
        $achievements = $achievementService->getAll(false);
        jsonResponse(['achievements' => $achievements]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения достижений: ' . $e->getMessage());
        jsonError('Ошибка получения достижений', 500);
    }
}

/**
 * GET /achievements/my - мои достижения с прогрессом
 */
function handleGetMyAchievements($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var AchievementService $achievementService */
        $achievementService = $container->get(AchievementService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $achievements = $achievementService->getUserAchievements($user->id, false);
        jsonResponse(['achievements' => $achievements]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения достижений пользователя: ' . $e->getMessage());
        jsonError('Ошибка получения достижений', 500);
    }
}

/**
 * GET /achievements/showcased - витрина достижений
 */
function handleGetShowcasedAchievements($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var AchievementService $achievementService */
        $achievementService = $container->get(AchievementService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $showcased = $achievementService->getShowcased($user->id);
        jsonResponse(['showcased' => $showcased]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения витрины достижений: ' . $e->getMessage());
        jsonError('Ошибка получения витрины', 500);
    }
}

/**
 * POST /achievements/showcase - настроить витрину
 */
function handleSetShowcasedAchievements($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var AchievementService $achievementService */
        $achievementService = $container->get(AchievementService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $achievementIds = $body['achievement_ids'] ?? [];
        if (!is_array($achievementIds)) {
            jsonError('Неверный формат данных', 400);
        }

        $achievementService->setShowcased($user->id, $achievementIds);
        jsonResponse(['message' => 'Витрина обновлена']);
    } catch (\Throwable $e) {
        error_log('Ошибка настройки витрины: ' . $e->getMessage());
        jsonError('Ошибка настройки витрины', 500);
    }
}

/**
 * GET /achievements/stats - статистика достижений
 */
function handleGetAchievementStats($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var AchievementService $achievementService */
        $achievementService = $container->get(AchievementService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $stats = $achievementService->getUserStats($user->id);
        jsonResponse($stats);
    } catch (\Throwable $e) {
        error_log('Ошибка получения статистики: ' . $e->getMessage());
        jsonError('Ошибка получения статистики', 500);
    }
}

/**
 * GET /collections - все коллекции с прогрессом
 */
function handleGetCollections($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var CollectionService $collectionService */
        $collectionService = $container->get(CollectionService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $collections = $collectionService->getUserCollections($user->id);
        jsonResponse(['collections' => $collections]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения коллекций: ' . $e->getMessage());
        jsonError('Ошибка получения коллекций', 500);
    }
}

/**
 * GET /collections/{id}/items - карточки коллекции
 */
function handleGetCollectionItems($container, ?array $telegramUser, int $collectionId): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var CollectionService $collectionService */
        $collectionService = $container->get(CollectionService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $items = $collectionService->getUserCollectionItems($user->id, $collectionId);
        jsonResponse(['items' => $items]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения карточек коллекции: ' . $e->getMessage());
        jsonError('Ошибка получения карточек', 500);
    }
}
