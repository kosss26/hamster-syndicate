#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelRound;
use GuzzleHttp\Client;
use Monolog\Logger;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$bootstrap = new AppBootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

/** @var Logger $logger */
$logger = $container->get(Logger::class);
/** @var Client $telegramClient */
$telegramClient = $container->get(GuzzleHttp\ClientInterface::class);

$duelId = (int) ($argv[1] ?? 0);
$roundId = (int) ($argv[2] ?? 0);
$chatId = (int) ($argv[3] ?? 0);
$messageId = (int) ($argv[4] ?? 0);
$startTime = (int) ($argv[5] ?? 0);
$originalText = $argv[6] ?? '';
$replyMarkup = $argv[7] ?? '{}';

if ($duelId === 0 || $roundId === 0 || $chatId === 0 || $messageId === 0 || $startTime === 0) {
    $logger->error('Недостаточно аргументов для скрипта duel_question_timer.php');
    exit(1);
}

$timeoutSeconds = 30;
$updateInterval = 1; // Обновляем каждую секунду

for ($i = 0; $i <= $timeoutSeconds; $i += $updateInterval) {
    $duel = Duel::query()->find($duelId);
    $round = DuelRound::query()->find($roundId);

    // Проверяем, что дуэль и раунд всё ещё активны
    if (!$duel instanceof Duel || !$round instanceof DuelRound) {
        $logger->info('Дуэль или раунд больше не активны, отмена таймера.', [
            'duel_id' => $duelId,
            'round_id' => $roundId,
        ]);
        exit(0);
    }

    // Проверяем, что раунд всё ещё текущий
    if ($duel->status !== 'in_progress') {
        $logger->info('Дуэль больше не в процессе, отмена таймера.', [
            'duel_id' => $duelId,
        ]);
        exit(0);
    }

    $elapsed = time() - $startTime;
    $remaining = max(0, $timeoutSeconds - $elapsed);

    if ($remaining <= 0) {
        // Время истекло - применяем таймауты для обоих участников
        try {
            $duelService = $container->get(\QuizBot\Application\Services\DuelService::class);
            $now = \Carbon\Carbon::now();
            
            $round->refresh();
            $duel->refresh();
            
            // Проверяем, что раунд всё ещё открыт
            if ($round->closed_at !== null) {
                $logger->info('Раунд уже закрыт, таймаут не применяется', [
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                ]);
                break;
            }
            
            // Применяем таймауты только если участник ещё не ответил
            $initiatorTimeout = $duelService->applyTimeoutIfNeeded($round, true, $now);
            $opponentTimeout = $duelService->applyTimeoutIfNeeded($round, false, $now);
            
            if ($initiatorTimeout || $opponentTimeout) {
                $logger->info('Таймаут применён для участников', [
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                    'initiator_timeout' => $initiatorTimeout,
                    'opponent_timeout' => $opponentTimeout,
                ]);
                
                $round->refresh();
                // Проверяем, можно ли завершить раунд
                $duelService->maybeCompleteRound($round);
                
                $round->refresh();
                if ($round->closed_at !== null) {
                    $duelService->maybeCompleteDuel($round->duel);
                    $logger->info('Раунд завершён после таймаута', [
                        'duel_id' => $duelId,
                        'round_id' => $roundId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $logger->error('Ошибка применения таймаута в скрипте таймера', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duel_id' => $duelId,
                'round_id' => $roundId,
            ]);
        }
        
        break;
    }

    // Обновляем текст сообщения с новым временем
    if ($messageId > 0 && $chatId !== 0) {
        // Заменяем строку с временем в оригинальном тексте
        $updatedText = preg_replace(
            '/⏱ Время на ответ: <b>\d+ сек\.<\/b>/',
            sprintf('⏱ Время на ответ: <b>%d сек.</b>', $remaining),
            $originalText
        );

        // Если не нашлось, пробуем другой формат
        if ($updatedText === $originalText) {
            $updatedText = preg_replace(
                '/⏱.*?сек\./',
                sprintf('⏱ Время на ответ: <b>%d сек.</b>', $remaining),
                $originalText
            );
        }

        try {
            $telegramClient->request('POST', 'editMessageText', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $updatedText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_decode($replyMarkup, true) ?: null,
                ],
            ]);
        } catch (\Throwable $e) {
            // Игнорируем ошибки редактирования
            if (strpos($e->getMessage(), 'message is not modified') === false && 
                strpos($e->getMessage(), 'message to edit not found') === false) {
                $logger->debug('Ошибка обновления таймера вопроса дуэли', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            }
        }
    }

    sleep($updateInterval);
}

$logger->info('Таймер вопроса дуэли истёк', [
    'duel_id' => $duelId,
    'round_id' => $roundId,
    'chat_id' => $chatId,
]);

exit(0);

