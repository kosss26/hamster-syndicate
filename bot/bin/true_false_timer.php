#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для динамического обновления таймера в режиме "Правда или ложь".
 * Запускается в фоне для каждого вопроса.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Infrastructure\Telegram\TelegramClientFactory;
use QuizBot\Infrastructure\Cache\CacheFactory;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\TrueFalseFact;

// Логирование
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function tfLog(string $message): void {
    global $logDir;
    file_put_contents(
        $logDir . '/tf_timer.log',
        sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message),
        FILE_APPEND
    );
}

tfLog("=== Скрипт запущен ===");
tfLog("Аргументы: " . json_encode($argv));

if ($argc < 9) {
    tfLog("Недостаточно аргументов: $argc");
    exit(1);
}

$chatId = $argv[1];
$messageId = (int) $argv[2];
$userId = (int) $argv[3];
$factId = (int) $argv[4];
$originalText = $argv[5];
$replyMarkupJson = $argv[6];
$timeoutSeconds = (int) $argv[7];
$streak = (int) $argv[8];

tfLog("chatId=$chatId, messageId=$messageId, userId=$userId, factId=$factId, timeout=$timeoutSeconds");

try {
    $bootstrap = new AppBootstrap(__DIR__ . '/..');
    $container = $bootstrap->getContainer();

    /** @var TelegramClientFactory $clientFactory */
    $clientFactory = $container->get(TelegramClientFactory::class);
    $telegramClient = $clientFactory->create();

    /** @var CacheFactory $cacheFactory */
    $cacheFactory = $container->get(CacheFactory::class);
    $cache = $cacheFactory->create();

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);

    $replyMarkup = json_decode($replyMarkupJson, true);
    $startTime = time();

    tfLog("Начинаем цикл таймера, startTime=$startTime");

    for ($elapsed = 0; $elapsed < $timeoutSeconds; $elapsed++) {
        sleep(1);
        $remaining = $timeoutSeconds - $elapsed - 1;

        // Проверяем, ответил ли пользователь
        $cacheKey = sprintf('tf_question_start:%d', $userId);
        $questionStartTime = $cache->get($cacheKey, static fn () => null);

        tfLog("elapsed=$elapsed, remaining=$remaining, questionStartTime=$questionStartTime");

        // Если время начала вопроса изменилось или отсутствует, значит пользователь ответил
        if ($questionStartTime === null || $questionStartTime > $startTime) {
            tfLog("Пользователь ответил, выходим");
            exit(0);
        }

        // Формируем обновлённый текст
        if ($remaining > 0) {
            $updatedText = preg_replace(
                '/⏱ <b>\d+ сек\.<\/b>/',
                sprintf('⏱ <b>%d сек.</b>', $remaining),
                $originalText
            );
        } else {
            // Время истекло
            $updatedText = preg_replace(
                '/⏱ <b>\d+ сек\.<\/b>/',
                '❌ <b>Время истекло!</b>',
                $originalText
            );
            // Убираем кнопки ответа
            $replyMarkup = ['inline_keyboard' => []];
        }

        tfLog("Обновляем сообщение, remaining=$remaining");

        // Обновляем сообщение
        try {
            $telegramClient->request('POST', 'editMessageText', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $updatedText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $replyMarkup,
                ],
            ]);
            tfLog("Сообщение обновлено успешно");
        } catch (\Throwable $e) {
            tfLog("Ошибка обновления: " . $e->getMessage());
            // Если сообщение уже изменено или удалено - выходим
            if (strpos($e->getMessage(), 'message is not modified') !== false ||
                strpos($e->getMessage(), "message can't be edited") !== false ||
                strpos($e->getMessage(), 'message to edit not found') !== false) {
                tfLog("Сообщение изменено/удалено, выходим");
                exit(0);
            }
        }

        if ($remaining <= 0) {
            break;
        }
    }

    // Время истекло - обрабатываем таймаут
    tfLog("Время истекло, обрабатываем таймаут");
    sleep(1);

    // Проверяем, не ответил ли пользователь в последний момент
    $cacheKey = sprintf('tf_question_start:%d', $userId);
    $questionStartTime = $cache->get($cacheKey, static fn () => null);

    tfLog("Финальная проверка: questionStartTime=$questionStartTime, startTime=$startTime");

    if ($questionStartTime !== null && $questionStartTime <= $startTime) {
        // Пользователь не ответил - засчитываем как неверный ответ и заканчиваем игру
        $user = User::query()->find($userId);

        if ($user instanceof User) {
            tfLog("Пользователь не ответил, обрабатываем как неверный");
            $result = $trueFalseService->handleAnswer($user, $factId, false);
            $result['timed_out'] = true;

            /** @var TrueFalseFact|null $fact */
            $fact = TrueFalseFact::query()->find($factId);
            
            if ($fact !== null) {
                $lines = [];
                $lines[] = '⏱ <b>Время истекло!</b>';
                $lines[] = '';
                $lines[] = '━━━━━━━━━━━━━━━━';
                $lines[] = '🏁 <b>ИГРА ОКОНЧЕНА</b>';
                $lines[] = '━━━━━━━━━━━━━━━━';
                $lines[] = '';
                $lines[] = '<b>Утверждение:</b>';
                $lines[] = htmlspecialchars($fact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines[] = '';
                $lines[] = sprintf('Правильный ответ: <b>%s</b>', $fact->is_true ? 'Правда' : 'Ложь');

                if (!empty($fact->explanation)) {
                    $lines[] = '';
                    $lines[] = htmlspecialchars($fact->explanation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                $lines[] = '';
                $lines[] = '━━━━━━━━━━━━━━━━';
                $lines[] = sprintf('📊 Твоя серия: <b>%d</b>', $streak);
                $lines[] = sprintf('🏆 Лучший результат: <b>%d</b>', (int) ($result['record'] ?? $user->profile?->true_false_record ?? 0));

                if ($result['record_updated'] ?? false) {
                    $lines[] = '';
                    $lines[] = '🎉 <b>Новый рекорд!</b>';
                }

                $telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => implode("\n", $lines),
                        'parse_mode' => 'HTML',
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔄 Играть снова', 'callback_data' => 'tf:start'],
                                ],
                            ],
                        ],
                    ],
                ]);
                tfLog("Финальное сообщение отправлено");
            }
        }
    }

    tfLog("=== Скрипт завершён ===");

} catch (\Throwable $e) {
    tfLog("ОШИБКА: " . $e->getMessage());
    tfLog("Trace: " . $e->getTraceAsString());
}
