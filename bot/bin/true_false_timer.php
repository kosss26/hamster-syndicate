#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для динамического обновления таймера в режиме "Правда или ложь".
 * Запускается в фоне для каждого вопроса.
 *
 * Аргументы:
 * 1. chatId
 * 2. messageId
 * 3. userId
 * 4. factId
 * 5. originalText
 * 6. replyMarkupJson
 * 7. timeoutSeconds
 * 8. streak
 */

require_once __DIR__ . '/../vendor/autoload.php';

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Infrastructure\Telegram\TelegramClientFactory;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Domain\Model\User;
use Symfony\Contracts\Cache\CacheInterface;

if ($argc < 9) {
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

try {
    $bootstrap = new AppBootstrap(__DIR__ . '/..');
    $container = $bootstrap->getContainer();

    /** @var TelegramClientFactory $clientFactory */
    $clientFactory = $container->get(TelegramClientFactory::class);
    $telegramClient = $clientFactory->create();

    /** @var CacheInterface $cache */
    $cache = $container->get(CacheInterface::class);

    /** @var TrueFalseService $trueFalseService */
    $trueFalseService = $container->get(TrueFalseService::class);

    $replyMarkup = json_decode($replyMarkupJson, true);
    $startTime = time();
    $updateInterval = 1; // Обновляем каждую секунду

    for ($elapsed = 0; $elapsed < $timeoutSeconds; $elapsed++) {
        sleep($updateInterval);
        $remaining = $timeoutSeconds - $elapsed - 1;

        // Проверяем, ответил ли пользователь
        $cacheKey = sprintf('tf_question_start:%d', $userId);
        $questionStartTime = $cache->get($cacheKey, static fn () => null);

        // Если время начала вопроса изменилось или отсутствует, значит пользователь ответил
        if ($questionStartTime === null || $questionStartTime > $startTime) {
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
        } catch (\Throwable $e) {
            // Если сообщение уже изменено или удалено - выходим
            if (strpos($e->getMessage(), 'message is not modified') !== false ||
                strpos($e->getMessage(), "message can't be edited") !== false ||
                strpos($e->getMessage(), 'message to edit not found') !== false) {
                exit(0);
            }
        }

        if ($remaining <= 0) {
            break;
        }
    }

    // Время истекло - обрабатываем таймаут
    sleep(1);

    // Проверяем, не ответил ли пользователь в последний момент
    $cacheKey = sprintf('tf_question_start:%d', $userId);
    $questionStartTime = $cache->get($cacheKey, static fn () => null);

    if ($questionStartTime !== null && $questionStartTime <= $startTime) {
        // Пользователь не ответил - засчитываем как неверный ответ
        $user = User::query()->find($userId);

        if ($user instanceof User) {
            $result = $trueFalseService->handleAnswer($user, $factId, false);
            $result['timed_out'] = true;

            // Отправляем результат
            $fact = $result['fact'];
            if ($fact !== null) {
                $lines = [];
                $lines[] = '❌ <b>Время истекло!</b>';
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
                $lines[] = sprintf('Текущая серия: <b>%d</b>', (int) $result['streak']);
                $lines[] = sprintf('Лучший результат: <b>%d</b>', (int) $result['record']);

                $telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => implode("\n", $lines),
                        'parse_mode' => 'HTML',
                    ],
                ]);

                // Пауза 3 секунды
                sleep(3);

                // Отправляем следующий вопрос
                $nextFact = $result['next_fact'] ?? null;
                if ($nextFact !== null) {
                    // Сохраняем время начала нового вопроса
                    $newCacheKey = sprintf('tf_question_start:%d', $userId);
                    $cache->delete($newCacheKey);
                    $newStartTime = time();
                    $cache->get($newCacheKey, static fn () => $newStartTime);

                    $newStreak = (int) $result['streak'];
                    $newTimeoutSeconds = 15;

                    $newLines = [
                        '🧠 <b>Правда или ложь</b>',
                        sprintf('⏱ <b>%d сек.</b>', $newTimeoutSeconds),
                    ];

                    if ($newStreak > 0) {
                        $newLines[] = sprintf('🔥 Серия: %d', $newStreak);
                    }

                    $newLines[] = '';
                    $newLines[] = htmlspecialchars($nextFact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $newLines[] = '';
                    $newLines[] = 'Выбери ответ:';

                    $newKeyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Правда', 'callback_data' => sprintf('tf:answer:%d:1', $nextFact->getKey())],
                                ['text' => '❌ Ложь', 'callback_data' => sprintf('tf:answer:%d:0', $nextFact->getKey())],
                            ],
                            [
                                ['text' => '⏭ Пропустить', 'callback_data' => 'tf:skip'],
                            ],
                        ],
                    ];

                    $response = $telegramClient->request('POST', 'sendMessage', [
                        'json' => [
                            'chat_id' => $chatId,
                            'text' => implode("\n", $newLines),
                            'parse_mode' => 'HTML',
                            'reply_markup' => $newKeyboard,
                        ],
                    ]);

                    // Запускаем новый таймер
                    $responseBody = json_decode($response->getBody()->getContents(), true);
                    $newMessageId = $responseBody['result']['message_id'] ?? null;

                    if ($newMessageId !== null) {
                        $phpPath = PHP_BINARY;
                        if (strpos($phpPath, 'fpm') !== false) {
                            $possiblePaths = ['/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php8.2', '/usr/bin/php8.1'];
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path) && is_executable($path)) {
                                    $phpPath = $path;
                                    break;
                                }
                            }
                        }

                        $command = sprintf(
                            'cd %s && nohup %s %s %s %d %d %d %s %s %d %d > /dev/null 2>&1 &',
                            escapeshellarg(__DIR__ . '/..'),
                            escapeshellarg($phpPath),
                            escapeshellarg(__FILE__),
                            escapeshellarg((string) $chatId),
                            $newMessageId,
                            $userId,
                            $nextFact->getKey(),
                            escapeshellarg(implode("\n", $newLines)),
                            escapeshellarg(json_encode($newKeyboard)),
                            $newTimeoutSeconds,
                            $newStreak
                        );

                        exec($command);
                    }
                } else {
                    $telegramClient->request('POST', 'sendMessage', [
                        'json' => [
                            'chat_id' => $chatId,
                            'text' => 'Факты закончились. Нажми /truth, чтобы сыграть снова.',
                        ],
                    ]);
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Логируем ошибку в файл
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents(
        $logDir . '/tf_timer_error.log',
        sprintf("[%s] Error: %s\nTrace: %s\n\n", date('Y-m-d H:i:s'), $e->getMessage(), $e->getTraceAsString()),
        FILE_APPEND
    );
}

