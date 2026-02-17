<?php

declare(strict_types=1);

use QuizBot\Infrastructure\Config\Config;
use QuizBot\Application\Services\CollectionService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Application\Services\TelegramPhotoService;
use QuizBot\Application\Services\TicketService;
use QuizBot\Application\Services\UserService;

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
    
    $mode = strtolower((string) ($body['mode'] ?? 'random'));
    $targetUserId = isset($body['target_user_id']) ? (int) $body['target_user_id'] : 0;
    $sourceDuelId = isset($body['source_duel_id']) ? (int) $body['source_duel_id'] : 0;
    
    // Очищаем зависшие matchmaking-дуэли (старше 60 секунд)
    $duelService->cleanupStaleMatchmakingDuels(60);

    // Проверяем, есть ли у пользователя активная дуэль (с автоочисткой старых matchmaking)
    $existingDuel = $duelService->findActiveDuelForUser($user, true);
    if ($existingDuel) {
        $existingSettings = is_array($existingDuel->settings) ? $existingDuel->settings : [];
        $isReplaceableRematchInvite = $mode === 'rematch'
            && $existingDuel->status === 'waiting'
            && (($existingSettings['rematch_invite'] ?? false) === true)
            && (int) $existingDuel->initiator_user_id === (int) $user->getKey();

        if (!$isReplaceableRematchInvite) {
            notifyDuelRealtime($existingDuel->getKey());
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
    }

    /** @var TicketService $ticketService */
    $ticketService = $container->get(TicketService::class);
    $ticketState = $ticketService->sync($user);
    if ((int) $ticketState['tickets'] < 1) {
        jsonError('Недостаточно билетов для дуэли', 409);
    }

    if ($mode === 'rematch') {
        if ($targetUserId <= 0) {
            jsonError('Не указан соперник для реванша', 400);
        }

        $target = \QuizBot\Domain\Model\User::query()->find($targetUserId);
        if (!$target) {
            jsonError('Соперник для реванша не найден', 404);
        }

        if ((int) $target->getKey() === (int) $user->getKey()) {
            jsonError('Нельзя отправить реванш самому себе', 400);
        }

        $targetActive = $duelService->findActiveDuelForUser($target, true);
        if ($targetActive) {
            jsonError('Соперник сейчас занят в другой дуэли', 409);
        }

        $sourceDuel = null;
        if ($sourceDuelId > 0) {
            $sourceDuel = $duelService->findById($sourceDuelId);
        }

        $duel = $duelService->createRematchInvite($user, $target, $sourceDuel);

        notifyDuelRealtime($duel->getKey());
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
            'code' => $duel->code,
            'initiator_id' => $duel->initiator_user_id,
            'opponent_id' => $duel->opponent_user_id,
            'mode' => 'rematch',
            'waiting' => true,
            'target_user' => [
                'id' => (int) $target->getKey(),
                'name' => (string) ($target->first_name ?: 'Соперник'),
            ],
            'expires_in' => 30,
        ]);
        return;
    }

    // Режим дуэли с другом (приватная дуэль по коду)
    if (\in_array($mode, ['invite', 'friend'], true)) {
        $duel = $duelService->createDuel($user, null, null, [
            'awaiting_target' => true,
        ]);

        notifyDuelRealtime($duel->getKey());
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
            'code' => $duel->code,
            'initiator_id' => $duel->initiator_user_id,
            'opponent_id' => $duel->opponent_user_id,
            'mode' => 'invite',
            'waiting' => true,
        ]);
        return;
    }

    // Поиск доступного matchmaking тикета от другого игрока (TTL 60 секунд)
    $availableTicket = $duelService->findAvailableMatchmakingTicket($user, 60);

    if ($availableTicket) {
        // Нашли соперника - присоединяемся и сразу стартуем дуэль
        $duel = $duelService->acceptDuel($availableTicket, $user);

        $ticketCharge = chargeDuelTicketsIfNeeded($container, $duel);
        if (!$ticketCharge['success']) {
            $duel->status = 'cancelled';
            $duel->finished_at = \Illuminate\Support\Carbon::now();
            $duel->save();
            jsonError($ticketCharge['error'] ?? 'Не удалось списать билет', 409);
        }

        $duel = $duelService->startDuel($duel);
        $duel->loadMissing('initiator.profile');

        notifyDuelRealtime($duel->getKey());
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
            'code' => $duel->code,
            'initiator_id' => $duel->initiator_user_id,
            'opponent_id' => $duel->opponent_user_id,
            'opponent' => $duel->initiator ? [
                'name' => $duel->initiator->first_name,
                'rating' => $duel->initiator->profile ? $duel->initiator->profile->rating : 0,
            ] : null,
            'matched' => true,
            'mode' => 'random',
        ]);
        return;
    }

    // Не нашли соперника - создаём matchmaking тикет
    $duel = $duelService->createMatchmakingTicket($user);

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'code' => $duel->code,
        'initiator_id' => $duel->initiator_user_id,
        'opponent_id' => $duel->opponent_user_id,
        'mode' => 'random',
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

    $duelSettings = is_array($duel->settings) ? $duel->settings : [];
    $isMatchmakingOwner = $duel->status === 'waiting'
        && $duel->opponent_user_id === null
        && $duel->initiator_user_id === $user->getKey();
    $ghostFallbackAttempted = false;
    $ghostFallbackAssigned = false;
    $ghostPoolAvailable = null;

    if ($isMatchmakingOwner && $duelService->isMatchmaking($duel) && $duelService->shouldUseGhostFallback($duel, 30)) {
        $ghostFallbackAttempted = true;
        $ghostPoolAvailable = $duelService->hasGhostSnapshotForUser($user);
        $ghostDuel = $duelService->assignGhostOpponentForMatchmaking($duel, $user);
        if ($ghostDuel) {
            $ghostFallbackAssigned = true;
            $duel = $ghostDuel;
            $duelSettings = is_array($duel->settings) ? $duel->settings : [];
            notifyDuelRealtime($duel->getKey());
        }
    }
    
    // Если дуэль matched но ещё не стартовала - стартуем
    if ($duel->status === 'matched' && $duel->started_at === null) {
        $ticketCharge = chargeDuelTicketsIfNeeded($container, $duel);
        if (!$ticketCharge['success']) {
            $duel->status = 'cancelled';
            $duel->finished_at = \Illuminate\Support\Carbon::now();
            $duel->save();
            jsonError($ticketCharge['error'] ?? 'Не удалось списать билет', 409);
        }

        $roundConfigs = [];
        $duelSettings = is_array($duel->settings) ? $duel->settings : [];
        if (($duelSettings['ghost_mode'] ?? false) === true && is_array($duelSettings['ghost_round_configs'] ?? null)) {
            $roundConfigs = $duelSettings['ghost_round_configs'];
        }

        $duel = $duelService->startDuel($duel, $roundConfigs);
        if (($duelSettings['ghost_mode'] ?? false) === true) {
            $updatedSettings = is_array($duel->settings) ? $duel->settings : [];
            unset($updatedSettings['ghost_round_configs']);
            $duel->settings = $updatedSettings;
            $duel->save();
        }
        notifyDuelRealtime($duel->getKey());
    }

    $duel->loadMissing('rounds.question.answers', 'rounds.question.category', 'initiator.profile', 'opponent.profile');
    $duelSettings = is_array($duel->settings) ? $duel->settings : [];
    $isGhostMatch = (($duelSettings['ghost_mode'] ?? false) === true) || (($duelSettings['match_type'] ?? '') === 'ghost');

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

    // Страховка: если активных открытых раундов больше нет, но дуэль ещё in_progress,
    // принудительно проверяем условия завершения (в т.ч. тех.поражение по таймаутам).
    if ($duel->status === 'in_progress' && $currentRound === null) {
        $duelService->maybeCompleteDuel($duel);
        $duel->refresh();
    }
    
    $question = null;
    $roundStatus = null;
    
    // Перезагружаем раунды после проверки таймаутов
    $duel->load('rounds.question.answers', 'rounds.question.category', 'result');
    
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
            'my_time_taken' => isset($lcMyPayload['time_elapsed']) ? (int) $lcMyPayload['time_elapsed'] : null,
            'opponent_time_taken' => isset($lcOpponentPayload['time_elapsed']) ? (int) $lcOpponentPayload['time_elapsed'] : null,
            'my_answered_at' => isset($lcMyPayload['answered_at']) ? (string) $lcMyPayload['answered_at'] : null,
            'opponent_answered_at' => isset($lcOpponentPayload['answered_at']) ? (string) $lcOpponentPayload['answered_at'] : null,
            'my_reason' => isset($lcMyPayload['reason']) ? (string) $lcMyPayload['reason'] : null,
            'opponent_reason' => isset($lcOpponentPayload['reason']) ? (string) $lcOpponentPayload['reason'] : null,
            'my_timed_out' => isset($lcMyPayload['reason']) && $lcMyPayload['reason'] === 'timeout',
            'opponent_timed_out' => isset($lcOpponentPayload['reason']) && $lcOpponentPayload['reason'] === 'timeout',
            'correct_answer_id' => $lcCorrectAnswerId,
            'closed_at' => $lastClosedRound->closed_at ? $lastClosedRound->closed_at->toIso8601String() : null,
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
            'id' => $q ? $q->getKey() : null,
            'text' => $q ? $q->question_text : null,
            'category' => ($q && $q->category) ? $q->category->title : 'Общие знания',
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
            'my_answer_id' => $myAnswered && isset($myPayload['answer_id']) ? (int) $myPayload['answer_id'] : null,
            'my_correct' => $myAnswered ? ($myPayload['is_correct'] ?? false) : null,
            'my_time_taken' => $myAnswered && isset($myPayload['time_elapsed']) ? (int) $myPayload['time_elapsed'] : null,
            'my_answered_at' => $myAnswered && isset($myPayload['answered_at']) ? (string) $myPayload['answered_at'] : null,
            'my_reason' => $myAnswered && isset($myPayload['reason']) ? (string) $myPayload['reason'] : null,
            'my_timed_out' => $myAnswered && isset($myPayload['reason']) && $myPayload['reason'] === 'timeout',
            'opponent_answered' => $opponentAnswered,
            'opponent_answer_id' => $opponentAnswered && isset($opponentPayload['answer_id']) ? (int) $opponentPayload['answer_id'] : null,
            'opponent_correct' => $opponentAnswered ? ($opponentPayload['is_correct'] ?? false) : null,
            'opponent_time_taken' => $opponentAnswered && isset($opponentPayload['time_elapsed']) ? (int) $opponentPayload['time_elapsed'] : null,
            'opponent_answered_at' => $opponentAnswered && isset($opponentPayload['answered_at']) ? (string) $opponentPayload['answered_at'] : null,
            'opponent_reason' => $opponentAnswered && isset($opponentPayload['reason']) ? (string) $opponentPayload['reason'] : null,
            'opponent_timed_out' => $opponentAnswered && isset($opponentPayload['reason']) && $opponentPayload['reason'] === 'timeout',
            'correct_answer_id' => ($myAnswered || $opponentAnswered) ? $correctAnswerId : null,
            'round_closed' => $currentRound->closed_at !== null,
            'time_limit' => $currentRound->time_limit ?? 30,
            'question_sent_at' => $currentRound->question_sent_at ? $currentRound->question_sent_at->toIso8601String() : null,
        ];
    }

    // Подсчитываем очки
    $initiatorScore = $duel->rounds->sum('initiator_score');
    $opponentScore = $duel->rounds->sum('opponent_score');
    $completedRounds = $duel->rounds->whereNotNull('closed_at')->count();
    $cancelReason = isset($duelSettings['cancel_reason']) ? (string) $duelSettings['cancel_reason'] : null;
    $isCancelledWithoutMatch = $duel->status === 'cancelled' && $completedRounds === 0;

    // Получаем изменение рейтинга, если дуэль завершена
    $ratingChange = 0;
    $duelAchievementUnlocks = [];
    if ($duel->status === 'finished') {
        $result = $duel->result;
        if ($result && isset($result->metadata['rating_changes'])) {
            $changes = $result->metadata['rating_changes'];
            $ratingChange = $isInitiator 
                ? ($changes['initiator_rating_change'] ?? 0)
                : ($changes['opponent_rating_change'] ?? 0);
        }
        if ($result && isset($result->metadata['achievement_unlocks']) && is_array($result->metadata['achievement_unlocks'])) {
            $unlockBucket = $isInitiator ? 'initiator' : 'opponent';
            $bucketData = $result->metadata['achievement_unlocks'][$unlockBucket] ?? [];
            if (is_array($bucketData)) {
                $duelAchievementUnlocks = $bucketData;
            }
        }
    }

    jsonResponse([
        'duel_id' => $duel->getKey(),
        'status' => $duel->status,
        'cancel_reason' => $cancelReason,
        'cancelled_without_match' => $isCancelledWithoutMatch,
        'code' => $duel->code,
        'match_type' => $isGhostMatch ? 'ghost' : 'live',
        'is_rematch' => (($duelSettings['rematch_invite'] ?? false) === true),
        'is_ghost_match' => $isGhostMatch,
        'ghost_fallback_attempted' => $ghostFallbackAttempted,
        'ghost_fallback_assigned' => $ghostFallbackAssigned,
        'ghost_pool_available' => $ghostPoolAvailable,
        'rating_change' => $ratingChange,
        'current_round' => $completedRounds + 1,
        'total_rounds' => $duel->rounds_to_win * 2,
        'initiator_score' => $initiatorScore,
        'opponent_score' => $opponentScore,
        'initiator' => $duel->initiator ? [
            'id' => $duel->initiator->getKey(),
            'name' => $duel->initiator->first_name,
            'rating' => $duel->initiator->profile ? $duel->initiator->profile->rating : 0,
            'photo_url' => $duel->initiator->photo_url,
        ] : null,
        'opponent' => $duel->opponent ? [
            'id' => $duel->opponent->getKey(),
            'name' => $duel->opponent->first_name,
            'rating' => $isGhostMatch
                ? (int) ($duelSettings['ghost_source_rating'] ?? ($duel->opponent->profile ? $duel->opponent->profile->rating : 0))
                : ($duel->opponent->profile ? $duel->opponent->profile->rating : 0),
            'photo_url' => $duel->opponent->photo_url,
            'is_ghost' => $isGhostMatch,
        ] : null,
        'question' => $question,
        'round_status' => $roundStatus,
        'last_closed_round' => $lastClosedRoundStatus,
        'is_initiator' => $isInitiator,
        'achievement_unlocks' => $duelAchievementUnlocks,
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
    $achievementUnlocks = $statisticsService->recordAnswer(
        $user,
        $round->question ? $round->question->category_id : null,
        $round->question ? $round->question->getKey() : null,
        $payload['is_correct'] ?? false,
        (int)(($payload['time_elapsed'] ?? 0) * 1000), // секунды в мс
        'duel'
    );

    /** @var CollectionService $collectionService */
    $collectionService = $container->get(CollectionService::class);
    $collectionDrop = $collectionService->awardDropForEvent($user->getKey(), 'duel', [
        'is_success' => (bool) ($payload['is_correct'] ?? false),
        'is_timeout' => (($payload['reason'] ?? null) === 'timeout'),
    ]);
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
    $opponentTimeTaken = $opponentAnswered && isset($opponentPayload['time_elapsed']) ? (int) $opponentPayload['time_elapsed'] : null;
    $opponentReason = $opponentAnswered && isset($opponentPayload['reason']) ? (string) $opponentPayload['reason'] : null;
    
    // Проверяем, закрыт ли раунд (оба ответили)
    $roundClosed = $round->closed_at !== null;

    $myTimeTaken = isset($payload['time_elapsed']) ? (int) $payload['time_elapsed'] : null;
    $myReason = isset($payload['reason']) ? (string) $payload['reason'] : null;
    $speedDeltaSeconds = null;
    if ($myTimeTaken !== null && $opponentTimeTaken !== null) {
        $speedDeltaSeconds = $opponentTimeTaken - $myTimeTaken;
    }

    $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;
    $xpGain = ($payload['is_correct'] ?? false) ? 12 : ($reason === 'timeout' ? 2 : 5);
    if ($speedDeltaSeconds !== null && $speedDeltaSeconds > 0) {
        $xpGain += 2;
    }
    $duelSettings = is_array($duel->settings) ? $duel->settings : [];
    $rewardFactor = (float) ($duelSettings['reward_factor'] ?? 1.0);
    if ($rewardFactor > 0 && $rewardFactor < 1) {
        $xpGain = max(1, (int) round($xpGain * $rewardFactor));
    }
    $xpResult = $userService->grantExperience($user, $xpGain);

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'round_id' => $round->getKey(),
        'is_correct' => $payload['is_correct'] ?? false,
        'my_answer_id' => isset($payload['answer_id']) ? (int) $payload['answer_id'] : null,
        'points_earned' => $payload['score'] ?? 0,
        'time_taken' => $myTimeTaken ?? 0,
        'my_time_taken' => $myTimeTaken,
        'my_reason' => $myReason,
        'my_timed_out' => $myReason === 'timeout',
        'correct_answer_id' => $correctAnswerId,
        'opponent_answered' => $opponentAnswered,
        'opponent_correct' => $opponentCorrect,
        'opponent_time_taken' => $opponentTimeTaken,
        'opponent_reason' => $opponentReason,
        'opponent_timed_out' => $opponentReason === 'timeout',
        'speed_delta_seconds' => $speedDeltaSeconds,
        'round_closed' => $roundClosed,
        'experience' => $xpResult,
        'achievement_unlocks' => $achievementUnlocks,
        'collection_drops' => $collectionDrop ? [$collectionDrop] : [],
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

    $settings = is_array($duel->settings) ? $duel->settings : [];
    if (($settings['rematch_invite'] ?? false) === true) {
        $settings['cancel_reason'] = 'rematch_cancelled_by_initiator';
        $settings['cancelled_by_user_id'] = (int) $user->getKey();
        $duel->settings = $settings;
    } elseif (($settings['matchmaking'] ?? false) === true) {
        $settings['cancel_reason'] = 'search_cancelled';
        $settings['cancelled_by_user_id'] = (int) $user->getKey();
        $duel->settings = $settings;
    }

    $duel->status = 'cancelled';
    $duel->finished_at = \Illuminate\Support\Carbon::now();
    $duel->save();

    notifyDuelRealtime($duel->getKey());
    jsonResponse(['cancelled' => true, 'duel_id' => $duelId]);
}

/**
 * Получить входящее приглашение на реванш
 */
function handleGetIncomingRematch($container, ?array $telegramUser): void
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
    $invite = $duelService->getIncomingRematchInvite($user);

    if (!$invite) {
        jsonResponse(['incoming' => null]);
    }

    $invite->loadMissing('initiator.profile');
    $settings = is_array($invite->settings) ? $invite->settings : [];
    $expiresAtRaw = (string) ($settings['rematch_expires_at'] ?? '');
    $secondsLeft = 0;
    if ($expiresAtRaw !== '') {
        try {
            $secondsLeft = max(0, \Illuminate\Support\Carbon::now()->diffInSeconds(\Illuminate\Support\Carbon::parse($expiresAtRaw), false));
        } catch (\Throwable $e) {
            $secondsLeft = 0;
        }
    }

    jsonResponse([
        'incoming' => [
            'duel_id' => (int) $invite->getKey(),
            'code' => (string) $invite->code,
            'created_at' => $invite->created_at ? $invite->created_at->toIso8601String() : null,
            'expires_in' => $secondsLeft,
            'initiator' => $invite->initiator ? [
                'id' => (int) $invite->initiator->getKey(),
                'name' => (string) ($invite->initiator->first_name ?: 'Соперник'),
                'rating' => (int) ($invite->initiator->profile ? $invite->initiator->profile->rating : 0),
                'photo_url' => $invite->initiator->photo_url,
            ] : null,
            'reward_factor' => (float) ($settings['reward_factor'] ?? \QuizBot\Application\Services\DuelService::REMATCH_REWARD_COEFFICIENT),
        ],
    ]);
}

/**
 * Принять приглашение на реванш
 */
function handleAcceptRematch($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $duelId = (int) ($body['duel_id'] ?? 0);
    if ($duelId <= 0) {
        jsonError('Не указан duel_id', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);

    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var TicketService $ticketService */
    $ticketService = $container->get(TicketService::class);
    $ticketState = $ticketService->sync($user);
    if ((int) $ticketState['tickets'] < 1) {
        jsonError('Недостаточно билетов для реванша', 409);
    }

    $duel = $duelService->findById($duelId);
    if (!$duel) {
        jsonError('Приглашение не найдено', 404);
    }

    try {
        $duel = $duelService->acceptRematchInvite($duel, $user);
    } catch (\Throwable $e) {
        jsonError($e->getMessage(), 409);
    }

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'duel_id' => (int) $duel->getKey(),
        'status' => (string) $duel->status,
        'accepted' => true,
    ]);
}

/**
 * Отклонить приглашение на реванш
 */
function handleDeclineRematch($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $duelId = (int) ($body['duel_id'] ?? 0);
    if ($duelId <= 0) {
        jsonError('Не указан duel_id', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $duel = $duelService->findById($duelId);
    if (!$duel) {
        jsonError('Приглашение не найдено', 404);
    }

    try {
        $duel = $duelService->declineRematchInvite($duel, $user);
    } catch (\Throwable $e) {
        jsonError($e->getMessage(), 409);
    }

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'duel_id' => (int) $duel->getKey(),
        'declined' => true,
    ]);
}

/**
 * Отменить отправленное приглашение на реванш
 */
function handleCancelRematch($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $duelId = (int) ($body['duel_id'] ?? 0);
    if ($duelId <= 0) {
        jsonError('Не указан duel_id', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    $duel = $duelService->findById($duelId);
    if (!$duel) {
        jsonError('Приглашение не найдено', 404);
    }

    try {
        $duel = $duelService->cancelRematchInvite($duel, $user);
    } catch (\Throwable $e) {
        jsonError($e->getMessage(), 409);
    }

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'duel_id' => (int) $duel->getKey(),
        'cancelled' => true,
    ]);
}

/**
 * Присоединение к дуэли по коду
 */
function handleJoinDuel($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $code = trim((string) ($body['code'] ?? ''));
    $code = preg_replace('/\D+/', '', $code) ?? '';

    if (!preg_match('/^\d{5}$/', $code)) {
        jsonError('Код дуэли должен содержать ровно 5 цифр', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    
    $user = $userService->findByTelegramId((int) $telegramUser['id']);
    
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var DuelService $duelService */
    $duelService = $container->get(DuelService::class);
    /** @var TicketService $ticketService */
    $ticketService = $container->get(TicketService::class);

    $ticketState = $ticketService->sync($user);
    if ((int) $ticketState['tickets'] < 1) {
        jsonError('Недостаточно билетов для дуэли', 409);
    }
    
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
    $duel = $duelService->acceptDuel($duel, $user);
    $ticketCharge = chargeDuelTicketsIfNeeded($container, $duel);
    if (!$ticketCharge['success']) {
        $duel->status = 'cancelled';
        $duel->finished_at = \Illuminate\Support\Carbon::now();
        $duel->save();
        jsonError($ticketCharge['error'] ?? 'Не удалось списать билет', 409);
    }
    $duel->loadMissing('initiator.profile');

    notifyDuelRealtime($duel->getKey());
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
 * Списывает билеты у участников, если для дуэли они еще не списаны.
 *
 * @return array{success:bool,error?:string}
 */
function chargeDuelTicketsIfNeeded($container, \QuizBot\Domain\Model\Duel $duel): array
{
    $duel->loadMissing('initiator.profile', 'opponent.profile');
    $settings = is_array($duel->settings) ? $duel->settings : [];

    /** @var TicketService $ticketService */
    $ticketService = $container->get(TicketService::class);

    // Сначала пред-проверка, чтобы избежать частичного списания.
    if ($duel->initiator && empty($settings['ticket_charged_initiator'])) {
        $state = $ticketService->sync($duel->initiator);
        if ((int) ($state['tickets'] ?? 0) < 1) {
            return ['success' => false, 'error' => 'У инициатора закончились билеты'];
        }
    }
    if ($duel->opponent && empty($settings['ticket_charged_opponent'])) {
        $state = $ticketService->sync($duel->opponent);
        if ((int) ($state['tickets'] ?? 0) < 1) {
            return ['success' => false, 'error' => 'У соперника закончились билеты'];
        }
    }

    if ($duel->initiator && empty($settings['ticket_charged_initiator'])) {
        $spent = $ticketService->spend($duel->initiator, 1);
        if (!$spent['success']) {
            return ['success' => false, 'error' => 'У инициатора закончились билеты'];
        }
        $settings['ticket_charged_initiator'] = true;
    }

    if ($duel->opponent && empty($settings['ticket_charged_opponent'])) {
        $spent = $ticketService->spend($duel->opponent, 1);
        if (!$spent['success']) {
            return ['success' => false, 'error' => 'У соперника закончились билеты'];
        }
        $settings['ticket_charged_opponent'] = true;
    }

    $duel->settings = $settings;
    $duel->save();

    return ['success' => true];
}

/**
 * Сигнализирует websocket-серверу о значимом изменении дуэли.
 */
function notifyDuelRealtime(int $duelId): void
{
    if ($duelId <= 0) {
        return;
    }

    $basePath = dirname(__DIR__);
    $eventsPath = $basePath . '/storage/runtime/duel_events';

    if (!is_dir($eventsPath)) {
        @mkdir($eventsPath, 0775, true);
    }

    $target = $eventsPath . '/duel_' . $duelId . '.signal';

    // Coalescing: если сигнал уже есть и еще не обработан websocket-сервером,
    // не перезаписываем его повторно.
    if (is_file($target)) {
        return;
    }

    @file_put_contents($target, sprintf('%.6f', microtime(true)), LOCK_EX);
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
function handleGetDuelWsTicket($container, ?array $telegramUser, ?int $duelId): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!$duelId || $duelId <= 0) {
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
    $duel = $duelService->findById($duelId);

    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }

    $isParticipant = (int) $duel->initiator_user_id === (int) $user->id
        || (int) ($duel->opponent_user_id ?? 0) === (int) $user->id;

    if (!$isParticipant) {
        jsonError('Доступ запрещён', 403);
    }

    if (in_array((string) $duel->status, ['finished', 'cancelled'], true)) {
        jsonError('Дуэль уже завершена', 409);
    }

    /** @var Config $config */
    $config = $container->get(Config::class);
    $secret = (string) $config->get('WEBSOCKET_TICKET_SECRET', $config->get('TELEGRAM_BOT_TOKEN', ''));

    if ($secret === '') {
        jsonError('Не настроен WEBSOCKET_TICKET_SECRET', 500);
    }

    $ticketTtlSeconds = max(30, (int) $config->get('WEBSOCKET_TICKET_TTL_SECONDS', 300));
    $issuedAt = time();
    $expiresAt = $issuedAt + $ticketTtlSeconds;
    $payload = [
        'duel_id' => $duelId,
        'user_id' => (int) $user->id,
        'iat' => $issuedAt,
        'exp' => $expiresAt,
        'jti' => bin2hex(random_bytes(8)),
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payloadJson, $secret);
    $encodedPayload = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');

    jsonResponse([
        'ticket' => $encodedPayload . '.' . $signature,
        'expires_at' => $expiresAt,
    ]);
}

/**
 * Получение текущей активной дуэли
 */
function handleGetActiveDuel($container, ?array $telegramUser): void
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
    
    // Очищаем старые дуэли
    $duelService->cleanupStaleMatchmakingDuels(60);
    
    // Ищем активную
    $duel = $duelService->findActiveDuelForUser($user, true);
    
    if ($duel) {
        jsonResponse([
            'duel_id' => $duel->getKey(),
            'status' => $duel->status,
        ]);
    } else {
        jsonResponse([
            'duel_id' => null
        ]);
    }
}
