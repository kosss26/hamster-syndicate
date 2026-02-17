<?php

declare(strict_types=1);

use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Application\Services\TelegramPhotoService;
use QuizBot\Application\Services\TicketService;
use QuizBot\Application\Services\UserService;

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

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    $activeDuel = $duelService->findActiveDuelForUser($user, false);

    jsonResponse([
        'id' => $user->getKey(),
        'telegram_id' => $user->telegram_id,
        'username' => $user->username,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'active_duel_id' => $activeDuel ? $activeDuel->getKey() : null,
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
    $experienceProgress = $userService->getExperienceProgress($profile);
    /** @var TicketService $ticketService */
    $ticketService = $container->get(TicketService::class);
    $ticketState = $ticketService->sync($user);

    jsonResponse([
        'level' => (int) $profile->level,
        'experience' => (int) $profile->experience,
        'experience_progress' => $experienceProgress,
        'rating' => (int) $profile->rating,
        'rank' => $rank,
        'coins' => (int) $profile->coins,
        'gems' => (int) $profile->gems,
        'tickets' => (int) $ticketState['tickets'],
        'ticket_cap' => (int) $ticketState['cap'],
        'ticket_regen_cap' => (int) $ticketState['regen_cap'],
        'ticket_seconds_to_next' => (int) $ticketState['seconds_to_next'],
        'ticket_next_at' => $ticketState['next_ticket_at'],
        'win_streak' => (int) $profile->streak_days,
        'true_false_record' => (int) $profile->true_false_record,
        'photo_url' => $user->photo_url,
        'equipped_frame' => $profile->equipped_frame ?? 'default',
        'stats' => [
            'duel_wins' => (int) $profile->duel_wins,
            'duel_losses' => (int) $profile->duel_losses,
            'duel_draws' => (int) $profile->duel_draws,
            'total_games' => (int) ($profile->duel_wins + $profile->duel_losses + $profile->duel_draws),
        ],
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
                'photo_url' => $user->photo_url, // Используем то что есть, не загружаем
                'equipped_frame' => $user->profile ? ($user->profile->equipped_frame ?? 'default') : 'default',
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
                'photo_url' => $user->photo_url, // Используем то что есть, не загружаем
                'equipped_frame' => $user->profile ? ($user->profile->equipped_frame ?? 'default') : 'default',
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
