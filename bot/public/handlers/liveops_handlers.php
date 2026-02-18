<?php

declare(strict_types=1);

use QuizBot\Application\Services\LiveOpsService;
use QuizBot\Application\Services\UserService;

function handleGetLiveOpsDashboard($container, ?array $telegramUser): void
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

    /** @var LiveOpsService $liveOps */
    $liveOps = $container->get(LiveOpsService::class);

    jsonResponse($liveOps->getDashboard($user));
}

function handleClaimLiveOpsReward($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $claimKey = trim((string) ($body['claim_key'] ?? ''));
    if ($claimKey === '') {
        jsonError('Не указан claim_key', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    $user = $userService->findByTelegramId((int) $telegramUser['id']);

    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    /** @var LiveOpsService $liveOps */
    $liveOps = $container->get(LiveOpsService::class);

    try {
        $result = $liveOps->claim($user, $claimKey);
        jsonResponse($result);
    } catch (\RuntimeException $e) {
        jsonError($e->getMessage(), 409);
    } catch (\InvalidArgumentException $e) {
        jsonError($e->getMessage(), 400);
    }
}
