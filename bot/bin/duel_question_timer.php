#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelRound;
use GuzzleHttp\Client;
use Monolog\Logger;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

// Логируем начало работы скрипта
error_log(sprintf('[DUEL_TIMER] Скрипт запущен: duel_id=%d, round_id=%d, chat_id=%d, message_id=%d', 
    $argv[1] ?? 0, $argv[2] ?? 0, $argv[3] ?? 0, $argv[4] ?? 0));

$bootstrap = new AppBootstrap($basePath);
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

$logger->info('Таймер дуэли запущен', [
    'duel_id' => $duelId,
    'round_id' => $roundId,
    'chat_id' => $chatId,
    'message_id' => $messageId,
]);

if ($duelId === 0 || $roundId === 0 || $chatId === 0 || $messageId === 0 || $startTime === 0) {
    $logger->error('Недостаточно аргументов для скрипта duel_question_timer.php');
    exit(1);
}

$timeoutSeconds = 30;
$updateInterval = 5; // Обновляем каждые 5 секунд

/**
 * Отправляет результаты раунда и следующий вопрос участникам дуэли
 */
function sendRoundResultsAndNextQuestion(Duel $duel, DuelRound $round, $telegramClient, $logger, $container, int $duelId): void
{
    try {
        $duel->loadMissing('rounds.question.answers', 'initiator', 'opponent', 'result');
        $round->loadMissing('question.answers');
        
        // Формируем результаты раунда
        $initiatorSummary = formatParticipantSummary($duel, $round, true);
        $opponentSummary = formatParticipantSummary($duel, $round, false);
        
        $duel->loadMissing('rounds');
        $initiatorTotalScore = $duel->rounds->sum('initiator_score');
        $opponentTotalScore = $duel->rounds->sum('opponent_score');
        
        $scoreLine = sprintf(
            '⚔️ Счёт матча: <b>%d — %d</b>',
            $initiatorTotalScore,
            $opponentTotalScore
        );
        
        $lines = [
            sprintf('📝 <b>Итоги раунда %d</b>', (int) $round->round_number),
            '',
        ];
        $lines = array_merge($lines, $initiatorSummary);
        $lines[] = '';
        $lines = array_merge($lines, $opponentSummary);
        $lines[] = '';
        $lines[] = $scoreLine;
        
        $resultText = implode("\n", $lines);
        
        // Отправляем результаты обоим участникам
        foreach ([$duel->initiator, $duel->opponent] as $participant) {
            if (!$participant instanceof \QuizBot\Domain\Model\User) {
                continue;
            }
            
            $chatId = $participant->telegram_id;
            if ($chatId === null) {
                continue;
            }
            
            try {
                $telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => $resultText,
                        'parse_mode' => 'HTML',
                    ],
                ]);
            } catch (\Throwable $e) {
                $logger->error('Ошибка отправки результатов раунда', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                ]);
            }
        }
        
        // Если дуэль завершена, отправляем финальные результаты
        if ($duel->status === 'finished' && $duel->result !== null) {
            $result = $duel->result;
            $initiatorScore = (int) $result->initiator_total_score;
            $opponentScore = (int) $result->opponent_total_score;
            
            $winnerName = 'Ничья';
            if ($result->winner_user_id !== null) {
                $winner = $result->winner_user_id === $duel->initiator_user_id
                    ? $duel->initiator
                    : $duel->opponent;
                $winnerName = formatUserName($winner);
            }
            
            $finalLines = [
                '🏁 <b>Дуэль завершена!</b>',
                '',
                sprintf('⚔️ Итоговый счёт: <b>%d — %d</b>', $initiatorScore, $opponentScore),
                '',
            ];
            
            if ($result->winner_user_id === null) {
                $finalLines[] = '🤝 <b>Ничья!</b> Оба игрока показали отличный результат!';
            } else {
                $finalLines[] = sprintf('🏆 <b>Победитель: %s</b>', $winnerName);
                $finalLines[] = '🎉 Поздравляем с победой!';
            }
            
            $finalText = implode("\n", $finalLines);
            
            foreach ([$duel->initiator, $duel->opponent] as $participant) {
                if (!$participant instanceof \QuizBot\Domain\Model\User) {
                    continue;
                }
                
                $chatId = $participant->telegram_id;
                if ($chatId === null) {
                    continue;
                }
                
                try {
                    $telegramClient->request('POST', 'sendMessage', [
                        'json' => [
                            'chat_id' => $chatId,
                            'text' => $finalText,
                            'parse_mode' => 'HTML',
                        ],
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('Ошибка отправки финальных результатов', [
                        'error' => $e->getMessage(),
                        'chat_id' => $chatId,
                    ]);
                }
            }
        } else {
            // Отправляем следующий вопрос
            $duelService = $container->get(\QuizBot\Application\Services\DuelService::class);
            $nextRound = $duelService->getCurrentRound($duel);
            
            if ($nextRound instanceof DuelRound) {
                // Запускаем отправку следующего вопроса через webhook или напрямую
                // Для простоты просто логируем - следующий вопрос отправится при следующем взаимодействии
                $logger->info('Следующий раунд готов к отправке', [
                    'duel_id' => $duelId,
                    'next_round_id' => $nextRound->getKey(),
                ]);
            }
        }
    } catch (\Throwable $e) {
        $logger->error('Ошибка отправки результатов раунда', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'duel_id' => $duel->getKey(),
            'round_id' => $round->getKey(),
        ]);
    }
}

function formatParticipantSummary(Duel $duel, DuelRound $round, bool $forInitiator): array
{
    $user = $forInitiator ? $duel->initiator : $duel->opponent;
    $payload = $forInitiator ? ($round->initiator_payload ?? []) : ($round->opponent_payload ?? []);
    
    $status = '⏳ ответ не получен';
    
    if (($payload['completed'] ?? false) === true) {
        if (($payload['reason'] ?? null) === 'timeout') {
            $status = '⏰ время вышло';
        } elseif (($payload['is_correct'] ?? false) === true) {
            $status = '✅ правильный ответ';
        } else {
            $status = '❌ неверный ответ';
        }
    }
    
    $answerText = null;
    if (isset($payload['answer_id']) && $round->relationLoaded('question') && $round->question instanceof \QuizBot\Domain\Model\Question) {
        $answer = $round->question->answers->firstWhere('id', $payload['answer_id']);
        if ($answer instanceof \QuizBot\Domain\Model\Answer) {
            $answerText = htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
    
    $lines = [
        sprintf('%s — %s', formatUserName($user), $status),
    ];
    
    if ($answerText !== null) {
        $lines[] = sprintf('Ответ: %s', $answerText);
    }
    
    if ($payload !== [] && isset($payload['time_elapsed'])) {
        $lines[] = sprintf('Время: %d сек.', (int) $payload['time_elapsed']);
    }
    
    return $lines;
}

function formatUserName(?\QuizBot\Domain\Model\User $user): string
{
    if (!$user instanceof \QuizBot\Domain\Model\User) {
        return 'Неизвестный игрок';
    }
    
    if (!empty($user->first_name)) {
        return htmlspecialchars($user->first_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    if (!empty($user->username)) {
        return htmlspecialchars('@' . $user->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    return sprintf('Игрок %d', (int) $user->getKey());
}

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
            
            $logger->info('Проверка таймаутов', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'initiator_timeout' => $initiatorTimeout,
                'opponent_timeout' => $opponentTimeout,
                'round_closed' => $round->closed_at !== null,
            ]);
            
            if ($initiatorTimeout || $opponentTimeout) {
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
                    
                    // Пауза 3 секунды перед отправкой результатов
                    sleep(3);
                    
                    // Отправляем результаты раунда и следующий вопрос
                    sendRoundResultsAndNextQuestion($round->duel, $round, $telegramClient, $logger, $container, $duelId);
                } else {
                    $logger->warning('Раунд не завершён после таймаута', [
                        'duel_id' => $duelId,
                        'round_id' => $roundId,
                        'initiator_payload' => $round->initiator_payload ?? [],
                        'opponent_payload' => $round->opponent_payload ?? [],
                    ]);
                }
            } else {
                // Если таймауты не применены, всё равно проверяем завершение раунда
                $round->refresh();
                $duelService->maybeCompleteRound($round);
                
                $round->refresh();
                if ($round->closed_at !== null) {
                    $duelService->maybeCompleteDuel($round->duel);
                    $logger->info('Раунд завершён (оба ответили)', [
                        'duel_id' => $duelId,
                        'round_id' => $roundId,
                    ]);
                    
                    // Пауза 3 секунды перед отправкой результатов
                    sleep(3);
                    
                    // Отправляем результаты раунда и следующий вопрос
                    sendRoundResultsAndNextQuestion($round->duel, $round, $telegramClient, $logger, $container, $duelId);
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
        // Пробуем разные варианты паттернов
        $updatedText = preg_replace(
            '/⏱ Время на ответ: <b>\d+ сек\.<\/b>/',
            sprintf('⏱ Время на ответ: <b>%d сек.</b>', $remaining),
            $originalText
        );

        // Если не нашлось, пробуем без HTML тегов
        if ($updatedText === $originalText) {
            $updatedText = preg_replace(
                '/⏱ Время на ответ: \d+ сек\./',
                sprintf('⏱ Время на ответ: %d сек.', $remaining),
                $originalText
            );
        }
        
        // Если всё ещё не нашли, ищем строку с временем и заменяем число
        if ($updatedText === $originalText) {
            $lines = explode("\n", $originalText);
            foreach ($lines as $idx => $line) {
                if (preg_match('/⏱.*?(\d+).*?сек/', $line)) {
                    $lines[$idx] = sprintf('⏱ Время на ответ: <b>%d сек.</b>', $remaining);
                    $updatedText = implode("\n", $lines);
                    break;
                }
            }
        }

        try {
            $response = $telegramClient->request('POST', 'editMessageText', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $updatedText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_decode($replyMarkup, true) ?: null,
                ],
            ]);
            
            // Логируем успешное обновление каждые 5 секунд или в последние 5 секунд
            if ($remaining % 5 === 0 || $remaining <= 5) {
                $logger->info('Таймер обновлён', [
                    'remaining' => $remaining,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки редактирования, но логируем для отладки
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'message is not modified') === false && 
                strpos($errorMsg, 'message to edit not found') === false &&
                strpos($errorMsg, 'Bad Request') === false &&
                strpos($errorMsg, 'message can\'t be edited') === false) {
                $logger->warning('Ошибка обновления таймера вопроса дуэли', [
                    'error' => $errorMsg,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'remaining' => $remaining,
                    'original_text_preview' => substr($originalText, 0, 100),
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

