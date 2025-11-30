#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\StoryProgress;
use QuizBot\Domain\Model\StoryStep;
use GuzzleHttp\Client;
use Monolog\Logger;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$bootstrap = new AppBootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

/** @var Logger $logger */
$logger = $container->get(Logger::class);
/** @var Client $telegramClient */
$telegramClient = $container->get(GuzzleHttp\ClientInterface::class);

$chatId = (int) ($argv[1] ?? 0);
$messageId = (int) ($argv[2] ?? 0);
$progressId = (int) ($argv[3] ?? 0);
$stepId = (int) ($argv[4] ?? 0);
$startTime = (int) ($argv[5] ?? 0);
$originalText = $argv[6] ?? '';

if ($chatId === 0 || $messageId === 0 || $progressId === 0 || $stepId === 0 || $startTime === 0) {
    $logger->error('Недостаточно аргументов для скрипта story_question_timer.php');
    exit(1);
}

$timeoutSeconds = 30;
$updateInterval = 1; // Обновляем каждую секунду

for ($i = 0; $i <= $timeoutSeconds; $i += $updateInterval) {
    $progress = StoryProgress::query()->find($progressId);
    $step = StoryStep::query()->find($stepId);

    // Проверяем, что прогресс и шаг всё ещё активны
    if (!$progress instanceof StoryProgress || !$step instanceof StoryStep) {
        $logger->info('Прогресс или шаг больше не активны, отмена таймера.', [
            'progress_id' => $progressId,
            'step_id' => $stepId,
        ]);
        exit(0);
    }

    // Проверяем, что пользователь всё ещё на этом шаге
    if ($progress->current_step_id !== $stepId) {
        $logger->info('Пользователь перешёл на другой шаг, отмена таймера.', [
            'progress_id' => $progressId,
            'step_id' => $stepId,
        ]);
        exit(0);
    }

    $elapsed = time() - $startTime;
    $remaining = max(0, $timeoutSeconds - $elapsed);

    if ($remaining <= 0) {
        break;
    }

    // Обновляем текст сообщения с новым временем
    if ($messageId > 0 && $chatId !== 0) {
        // Заменяем строку с временем в оригинальном тексте
        $updatedText = preg_replace(
            '/⏱ У тебя \d+ секунд\./',
            sprintf('⏱ У тебя %d секунд.', $remaining),
            $originalText
        );

        // Если не нашлось, добавляем/обновляем строку с таймером
        if ($updatedText === $originalText) {
            // Ищем строку с таймером в другом формате
            $updatedText = preg_replace(
                '/⏱.*?секунд/',
                sprintf('⏱ У тебя %d секунд. Чем быстрее ответишь, тем больше очков получишь!', $remaining),
                $originalText
            );
            
            // Если всё ещё не нашлось, добавляем в конец
            if ($updatedText === $originalText && strpos($originalText, '⏱') === false) {
                $lines = explode("\n", $originalText);
                // Ищем строку с вопросом и добавляем таймер после неё
                $inserted = false;
                foreach ($lines as $idx => $line) {
                    if (strpos($line, '```') !== false && !$inserted) {
                        // Нашли блок с вопросом, добавляем таймер после него
                        array_splice($lines, $idx + 1, 0, '');
                        array_splice($lines, $idx + 2, 0, sprintf('⏱ У тебя %d секунд. Чем быстрее ответишь, тем больше очков получишь!', $remaining));
                        $inserted = true;
                        break;
                    }
                }
                if ($inserted) {
                    $updatedText = implode("\n", $lines);
                } else {
                    // Просто добавляем в конец
                    $updatedText = $originalText . "\n\n" . sprintf('⏱ У тебя %d секунд. Чем быстрее ответишь, тем больше очков получишь!', $remaining);
                }
            }
        }

        try {
            $telegramClient->request('POST', 'editMessageText', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $updatedText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_decode($argv[7] ?? '{}', true) ?: null,
                ],
            ]);
        } catch (\Throwable $e) {
            // Игнорируем ошибки редактирования (сообщение могло быть удалено или изменено)
            if (strpos($e->getMessage(), 'message is not modified') === false) {
                $logger->debug('Ошибка обновления таймера вопроса истории', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            }
        }
    }

    sleep($updateInterval);
}

// Время истекло - можно отправить уведомление или обработать автоматически
$logger->info('Таймер вопроса истории истёк', [
    'progress_id' => $progressId,
    'step_id' => $stepId,
    'chat_id' => $chatId,
]);

exit(0);

