#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelRound;
use GuzzleHttp\Client;
use Monolog\Logger;
use Illuminate\Support\Carbon;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

// Логируем начало работы скрипта
error_log(sprintf('[DUEL_TIMER] Скрипт запущен: duel_id=%d, round_id=%d, chat_id=%d, message_id=%d', 
    $argv[1] ?? 0, $argv[2] ?? 0, $argv[3] ?? 0, $argv[4] ?? 0));

// Создаём временный логгер для логирования до инициализации контейнера
$tempLogFile = dirname(__DIR__) . '/storage/logs/timer_debug.log';
@file_put_contents($tempLogFile, sprintf("[%s] Скрипт начал работу\n", date('Y-m-d H:i:s')), FILE_APPEND);

@file_put_contents($tempLogFile, sprintf("[%s] Создание bootstrap\n", date('Y-m-d H:i:s')), FILE_APPEND);

$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

@file_put_contents($tempLogFile, sprintf("[%s] Контейнер создан\n", date('Y-m-d H:i:s')), FILE_APPEND);

/** @var Logger $logger */
$logger = $container->get(Logger::class);
/** @var \QuizBot\Infrastructure\Telegram\TelegramClientFactory $telegramFactory */
$telegramFactory = $container->get(\QuizBot\Infrastructure\Telegram\TelegramClientFactory::class);
/** @var Client $telegramClient */
$telegramClient = $telegramFactory->create();

$duelId = (int) ($argv[1] ?? 0);
$roundId = (int) ($argv[2] ?? 0);
$chatId = (int) ($argv[3] ?? 0);
$messageId = (int) ($argv[4] ?? 0);
$startTime = (int) ($argv[5] ?? 0);
$originalText = $argv[6] ?? '';
$replyMarkup = $argv[7] ?? '{}';
$hasImage = (int) ($argv[8] ?? 0) === 1;

if ($duelId === 0 || $roundId === 0 || $chatId === 0 || $messageId === 0 || $startTime === 0) {
    $logger->error('Недостаточно аргументов для скрипта duel_question_timer.php');
    exit(1);
}

$timeoutSeconds = 30;
$updateInterval = 1; // Обновляем каждую секунду

$logger->info('Таймер дуэли запущен', [
    'duel_id' => $duelId,
    'round_id' => $roundId,
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'start_time' => $startTime,
    'timeout_seconds' => $timeoutSeconds,
    'update_interval' => $updateInterval,
]);

/**
 * Отправляет результаты раунда и следующий вопрос участникам дуэли
 */
function sendRoundResultsAndNextQuestion(Duel $duel, DuelRound $round, $telegramClient, $logger, $container, int $duelId): void
{
    // Блокировка: только один скрипт должен отправлять результаты
    $lockFile = dirname(__DIR__) . '/storage/logs/round_' . $round->getKey() . '_lock';
    $lockHandle = @fopen($lockFile, 'x');
    
    if ($lockHandle === false) {
        // Файл блокировки уже существует - другой скрипт уже обрабатывает этот раунд
        $logger->info('Другой скрипт уже обрабатывает результаты раунда', [
            'duel_id' => $duelId,
            'round_id' => $round->getKey(),
        ]);
        return;
    }
    
    // Устанавливаем блокировку на 10 секунд
    fwrite($lockHandle, (string) getmypid());
    fclose($lockHandle);
    
    // Удаляем блокировку через 10 секунд (на случай, если скрипт упадёт)
    register_shutdown_function(function() use ($lockFile) {
        @unlink($lockFile);
    });
    
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
            // Пауза 3 секунды после отправки результатов перед следующим вопросом
            sleep(3);
            
            // Отправляем следующий вопрос напрямую
            $duelService = $container->get(\QuizBot\Application\Services\DuelService::class);
            $nextRound = $duelService->getCurrentRound($duel);
            
            if ($nextRound instanceof DuelRound) {
                $logger->info('Отправка следующего вопроса', [
                    'duel_id' => $duelId,
                    'next_round_id' => $nextRound->getKey(),
                ]);
                
                // Отправляем следующий вопрос напрямую
                sendNextDuelQuestion($duel, $nextRound, $telegramClient, $logger, $container);
            } else {
                $logger->info('Следующий раунд не найден, дуэль завершена', [
                    'duel_id' => $duelId,
                ]);
                // Страховка: если следующий раунд отсутствует, но статус не обновлён до finished,
                // принудительно проверяем завершение матча.
                $duelService->maybeCompleteDuel($duel);
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

/**
 * Отправляет следующий вопрос дуэли участникам
 */
function sendNextDuelQuestion(Duel $duel, DuelRound $round, $telegramClient, $logger, $container): void
{
    try {
        $duelService = $container->get(\QuizBot\Application\Services\DuelService::class);
        $round = $duelService->markRoundDispatched($round);
        $round->loadMissing('question.answers', 'duel.initiator', 'duel.opponent');
        
        /** @var \QuizBot\Domain\Model\Question|null $question */
        $question = $round->question;
        
        if ($question === null) {
            $logger->error('В раунде дуэли отсутствует вопрос', [
                'duel_id' => $duel->getKey(),
                'round_id' => $round->getKey(),
            ]);
            return;
        }
        
        $timeLimit = $round->time_limit ?? 30;
        $totalRounds = $duel->rounds_to_win * 2;
        $currentRound = (int) $round->round_number;
        
        // Загружаем все раунды для отображения прогресса
        $duel->loadMissing('rounds');
        $allRounds = $duel->rounds->sortBy('round_number');
        
        // Создаём MessageFormatter для прогресс-бара
        $formatter = new \QuizBot\Application\Services\MessageFormatter();
        
        $baseLines = [];
        
        // Заголовок раунда
        $roundHeader = sprintf('РАУНД %d ИЗ %d', $currentRound, $totalRounds);
        $baseLines[] = sprintf('<b><strong>%s</strong></b>', $roundHeader);
        $baseLines[] = '';
        
        $baseLines[] = sprintf('⏱ Время на ответ: <b>%d сек.</b>', $timeLimit);
        $baseLines[] = '';
        $baseLines[] = sprintf('❓ <b>%s</b>', htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $baseLines[] = '';
        
        // Перемешиваем ответы
        $answers = $question->answers->shuffle();
        
        $buttons = [];
        $row = [];
        
        foreach ($answers as $index => $answer) {
            $row[] = [
                'text' => htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'callback_data' => sprintf('duel-answer:%d:%d:%d', $duel->getKey(), $round->getKey(), $answer->getKey()),
            ];
            
            if (count($row) === 2 || $index === count($answers) - 1) {
                $buttons[] = $row;
                $row = [];
            }
        }
        
        $replyMarkup = [
            'inline_keyboard' => $buttons,
        ];
        
        // Отправляем вопрос каждому участнику с правильным прогресс-баром
        foreach ([$duel->initiator, $duel->opponent] as $participant) {
            if (!$participant instanceof \QuizBot\Domain\Model\User) {
                continue;
            }
            
            $chatId = $participant->telegram_id;
            if ($chatId === null) {
                continue;
            }
            
            // Создаём кастомный прогресс-бар для каждого участника
            $customLines = $baseLines;
            $userId = $participant->getKey();
            $progressBar = $formatter->formatDuelProgress($currentRound, $totalRounds, $allRounds, $userId);
            // Вставляем прогресс-бар после заголовка и пустой строки
            array_splice($customLines, 2, 0, $progressBar);
            array_splice($customLines, 3, 0, ''); // Пустая строка после прогресс-бара
            
            $text = implode("\n", $customLines);
            
            try {
                $response = $telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => $text,
                        'parse_mode' => 'HTML',
                        'reply_markup' => $replyMarkup,
                    ],
                ]);
                
                $responseData = json_decode($response->getBody()->getContents(), true);
                $messageId = isset($responseData['result']['message_id']) ? (int) $responseData['result']['message_id'] : 0;
                
                if ($messageId > 0) {
                    // Запускаем таймер для этого вопроса
                    $basePath = dirname(__DIR__);
                    $scriptPath = $basePath . '/bin/duel_question_timer.php';
                    $startTime = time();
                    $replyMarkupJson = json_encode($replyMarkup);
                    
                    // Определяем PHP CLI
                    $phpPath = 'php';
                    $possiblePaths = ['/usr/bin/php', '/usr/bin/php8.2', '/usr/bin/php8.1', '/usr/bin/php8.0', '/usr/bin/php7.4'];
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path) && !is_dir($path)) {
                            $realPath = realpath($path);
                            if ($realPath !== false && strpos($realPath, 'fpm') === false) {
                                $phpPath = $path;
                                break;
                            }
                        }
                    }
                    
                    $logFile = $basePath . '/storage/logs/timer.log';
                    $logDir = dirname($logFile);
                    if (!is_dir($logDir)) {
                        @mkdir($logDir, 0775, true);
                    }
                    
                    $command = sprintf(
                        'cd %s && nohup %s %s %d %d %d %d %d %s %s >> %s 2>&1 & echo $!',
                        escapeshellarg($basePath),
                        escapeshellarg($phpPath),
                        escapeshellarg($scriptPath),
                        $duel->getKey(),
                        $round->getKey(),
                        $chatId,
                        $messageId,
                        $startTime,
                        escapeshellarg($text),
                        escapeshellarg($replyMarkupJson),
                        escapeshellarg($logFile)
                    );
                    
                    $processId = trim((string) shell_exec($command));
                    $logger->info('Таймер запущен для следующего вопроса', [
                        'duel_id' => $duel->getKey(),
                        'round_id' => $round->getKey(),
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'process_id' => $processId,
                    ]);
                }
            } catch (\Throwable $e) {
                $logger->error('Ошибка отправки следующего вопроса', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'duel_id' => $duel->getKey(),
                    'round_id' => $round->getKey(),
                ]);
            }
        }
    } catch (\Throwable $e) {
        $logger->error('Ошибка отправки следующего вопроса дуэли', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'duel_id' => $duel->getKey(),
            'round_id' => $round->getKey(),
        ]);
    }
}

$logger->info('Начало цикла таймера', [
    'duel_id' => $duelId,
    'round_id' => $roundId,
    'iterations' => (int) ($timeoutSeconds / $updateInterval) + 1,
]);

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

    // Проверяем, что раунд всё ещё текущий и не закрыт
    if ($duel->status !== 'in_progress' || $round->closed_at !== null) {
        $logger->info('Дуэль больше не в процессе или раунд закрыт, отмена таймера.', [
            'duel_id' => $duelId,
            'round_id' => $roundId,
            'duel_status' => $duel->status,
            'round_closed' => $round->closed_at !== null,
        ]);
        exit(0);
    }

    // Используем question_sent_at из базы для точного расчета времени
    $round->refresh();
    if ($round->question_sent_at === null) {
        $logger->warning('question_sent_at не установлен, используем startTime', [
            'duel_id' => $duelId,
            'round_id' => $roundId,
        ]);
        $elapsed = time() - $startTime;
    } else {
        $elapsed = $round->question_sent_at->diffInSeconds(Carbon::now());
    }
    
    $remaining = max(0, $timeoutSeconds - $elapsed);

    if ($remaining <= 0) {
        // Время истекло - применяем таймауты для обоих участников
        try {
            $duelService = $container->get(\QuizBot\Application\Services\DuelService::class);
            $now = Carbon::now();
            
            $round->refresh();
            $duel->refresh();
            
            // Проверяем, что раунд всё ещё открыт (если уже закрыт, значит оба ответили)
            if ($round->closed_at !== null) {
                $logger->info('Раунд уже закрыт (оба ответили), таймаут не нужен', [
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                ]);
                break;
            }
            
            $logger->info('Время истекло, применяем таймауты', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'elapsed' => $elapsed,
                'time_limit' => $timeoutSeconds,
                'question_sent_at' => $round->question_sent_at?->toAtomString(),
            ]);
            
            // Проверяем состояние участников до применения таймаутов
            $initiatorPayloadBefore = $round->initiator_payload ?? [];
            $opponentPayloadBefore = $round->opponent_payload ?? [];
            
            $logger->info('Состояние участников до таймаутов', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'initiator_done' => isset($initiatorPayloadBefore['completed']) && $initiatorPayloadBefore['completed'] === true,
                'opponent_done' => isset($opponentPayloadBefore['completed']) && $opponentPayloadBefore['completed'] === true,
            ]);
            
            // Применяем таймауты для обоих участников (даже если скрипт запущен только для одного)
            $initiatorTimeout = $duelService->applyTimeoutIfNeeded($round, true, $now);
            $opponentTimeout = $duelService->applyTimeoutIfNeeded($round, false, $now);
            
            // Обновляем раунд после применения таймаутов
            $round->refresh();
            
            $logger->info('Проверка таймаутов после истечения времени', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'initiator_timeout' => $initiatorTimeout,
                'opponent_timeout' => $opponentTimeout,
                'initiator_payload_after' => $round->initiator_payload ?? [],
                'opponent_payload_after' => $round->opponent_payload ?? [],
            ]);
            
            // Проверяем состояние участников перед вызовом maybeCompleteRound
            $initiatorDoneBefore = isset($round->initiator_payload['completed']) && $round->initiator_payload['completed'] === true;
            $opponentDoneBefore = isset($round->opponent_payload['completed']) && $round->opponent_payload['completed'] === true;
            
            $logger->info('Перед вызовом maybeCompleteRound', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'initiator_done' => $initiatorDoneBefore,
                'opponent_done' => $opponentDoneBefore,
                'initiator_payload' => $round->initiator_payload ?? [],
                'opponent_payload' => $round->opponent_payload ?? [],
            ]);
            
            // Проверяем, можно ли завершить раунд
            $duelService->maybeCompleteRound($round);
            
            $round->refresh();
            
            $logger->info('После вызова maybeCompleteRound', [
                'duel_id' => $duelId,
                'round_id' => $roundId,
                'round_closed' => $round->closed_at !== null,
                'initiator_payload' => $round->initiator_payload ?? [],
                'opponent_payload' => $round->opponent_payload ?? [],
            ]);
            
            if ($round->closed_at !== null) {
                $duelService->maybeCompleteDuel($round->duel);
                $logger->info('Раунд завершён после таймаута', [
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                ]);
                
                // Отправляем результаты раунда и следующий вопрос
                // Задержка 3 секунды будет внутри функции sendRoundResultsAndNextQuestion
                sendRoundResultsAndNextQuestion($round->duel, $round, $telegramClient, $logger, $container, $duelId);
            } else {
                // Проверяем, почему раунд не завершился
                $initiatorDone = isset($round->initiator_payload['completed']) && $round->initiator_payload['completed'] === true;
                $opponentDone = isset($round->opponent_payload['completed']) && $round->opponent_payload['completed'] === true;
                
                $logger->warning('Раунд не завершён после таймаута', [
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                    'initiator_done' => $initiatorDone,
                    'opponent_done' => $opponentDone,
                    'initiator_payload' => $round->initiator_payload ?? [],
                    'opponent_payload' => $round->opponent_payload ?? [],
                    'both_done' => $initiatorDone && $opponentDone,
                ]);
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

    // Обновляем текст сообщения с новым временем (только если раунд ещё открыт)
    if ($messageId > 0 && $chatId !== 0 && $round->closed_at === null) {
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
            // Если сообщение было отправлено как фото, используем editMessageCaption
            if ($hasImage) {
                $response = $telegramClient->request('POST', 'editMessageCaption', [
                    'json' => [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'caption' => $updatedText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_decode($replyMarkup, true) ?: null,
                    ],
                ]);
            } else {
                $response = $telegramClient->request('POST', 'editMessageText', [
                    'json' => [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $updatedText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_decode($replyMarkup, true) ?: null,
                    ],
                ]);
            }
            
            // Логируем успешное обновление каждые 10 секунд или в последние 5 секунд
            if ($remaining % 10 === 0 || $remaining <= 5) {
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
