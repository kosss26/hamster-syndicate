#!/usr/bin/env php
<?php

declare(strict_types=1);

use Monolog\Logger;
use QuizBot\Application\Services\DuelService;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\User;
use QuizBot\Infrastructure\Telegram\TelegramClientFactory;

require __DIR__ . '/../vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php matchmaking_timeout.php <duel_id> <timeout> <chat_id> [message_id]\n");

    exit(1);
}

$duelId = (int) $argv[1];
$timeout = (int) $argv[2];
$chatId = (int) $argv[3];
$messageId = isset($argv[4]) ? (int) $argv[4] : 0;

if ($timeout < 1 || $timeout > 300) {
    $timeout = 30;
}

$bootstrap = new AppBootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

/** @var DuelService $duelService */
$duelService = $container->get(DuelService::class);
/** @var Logger $logger */
$logger = $container->get(Logger::class);
/** @var TelegramClientFactory $telegramClientFactory */
$telegramClientFactory = $container->get(TelegramClientFactory::class);

$duel = $duelService->findById($duelId);

if ($duel === null) {
    exit(0);
}

$client = $telegramClientFactory->create();

if ($messageId === 0) {
    $logger->warning('Matchmaking: message_id не передан, таймер обновляться не будет', [
        'duel_id' => $duelId,
    ]);
}

$step = 5;
$remaining = $timeout;

while ($remaining > 0) {
    $sleep = min($step, $remaining);
    sleep($sleep);
    $remaining -= $sleep;

    $duel = $duelService->findById($duelId);

    if ($duel === null || $duel->status !== 'waiting' || !$duelService->isMatchmaking($duel)) {
        exit(0);
    }

    if ($messageId > 0 && $chatId !== 0) {
        $text = sprintf("🎲 Ищу случайного соперника...\n⏱ Осталось: %d сек.", max(0, $remaining));

        try {
            $client->request('POST', 'editMessageText', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ],
            ]);
        } catch (\Throwable $exception) {
            $logger->debug('Не удалось обновить таймер матчмейкинга', [
                'error' => $exception->getMessage(),
                'duel_id' => $duelId,
            ]);
        }
    }
}

$duel = $duelService->findById($duelId);

if ($duel === null || $duel->status !== 'waiting' || !$duelService->isMatchmaking($duel)) {
    exit(0);
}

$initiator = $duel->initiator()->first();

if (!$initiator instanceof User) {
    $logger->warning('Matchmaking timeout: инициатор не найден', [
        'duel_id' => $duelId,
    ]);

    exit(0);
}

$duelService->cancelWaitingDuel($duel, $initiator);

$text = "😔 Соперник не найден.\nПопробуй поиск ещё раз чуть позже.";

if ($messageId > 0 && $chatId !== 0) {
    try {
        $client->request('POST', 'editMessageText', [
            'json' => [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
        ]);

        exit(0);
    } catch (\Throwable $exception) {
        $logger->debug('Не удалось обновить сообщение о завершении матчмейкинга', [
            'error' => $exception->getMessage(),
            'duel_id' => $duelId,
        ]);
    }
}

try {
    $client->request('POST', 'sendMessage', [
        'json' => [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ],
    ]);
} catch (\Throwable $exception) {
    $logger->error('Не удалось отправить уведомление о тайм-ауте матчмейкинга', [
        'error' => $exception->getMessage(),
        'duel_id' => $duelId,
    ]);
}

