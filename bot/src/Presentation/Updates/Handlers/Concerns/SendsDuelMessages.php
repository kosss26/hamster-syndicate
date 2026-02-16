<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates\Handlers\Concerns;

use QuizBot\Domain\Model\Answer;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelResult;
use QuizBot\Domain\Model\DuelRound;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\User;
use QuizBot\Application\Services\DuelService;
use GuzzleHttp\ClientInterface;
use Monolog\Logger;

trait SendsDuelMessages
{
    abstract protected function getTelegramClient(): ClientInterface;

    abstract protected function getLogger(): Logger;

    abstract protected function getDuelService(): DuelService;
    
    /**
     * Возвращает клавиатуру с основными кнопками меню
     */
    protected function getMainKeyboard(): array
    {
        $webappUrl = getenv('WEBAPP_URL');
        if (empty($webappUrl)) {
            return ['remove_keyboard' => true];
        }

        $inlineKeyboard = [];
        $inlineKeyboard[] = [
            ['text' => '🎮 Играть', 'web_app' => ['url' => $webappUrl]],
        ];
        return ['inline_keyboard' => $inlineKeyboard];
    }
    
    /**
     * Устанавливает постоянную клавиатуру с основными кнопками
     * @param int|string $chatId
     */
    protected function setMainKeyboard($chatId): void
    {
        try {
            $response = $this->getTelegramClient()->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => ' ',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
            
            $responseBody = (string) $response->getBody();
            $responseData = json_decode($responseBody, true);
            $messageId = $responseData['result']['message_id'] ?? null;
            
            if ($messageId !== null) {
                // Удаляем служебное сообщение через небольшую задержку
                sleep(1);
                $this->getTelegramClient()->request('POST', 'deleteMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки при установке клавиатуры
        }
    }

    private function sendDuelQuestion(Duel $duel, DuelRound $round): void
    {
        $round = $this->getDuelService()->markRoundDispatched($round);
        $round->loadMissing('question.answers', 'duel.initiator', 'duel.opponent');

        /** @var Question|null $question */
        $question = $round->question;

        if ($question === null) {
            $this->getLogger()->error('В раунде дуэли отсутствует вопрос', [
                'duel_id' => $duel->getKey(),
                'round_id' => $round->getKey(),
            ]);

            return;
        }

        $timeLimit = $round->time_limit ?? 30;
        $totalRounds = $duel->rounds_to_win * 2 - 1;
        $currentRound = (int) $round->round_number;

        // Загружаем все раунды для отображения прогресса
        $duel->loadMissing('rounds');
        $allRounds = $duel->rounds->sortBy('round_number');

        // Используем MessageFormatter если доступен
        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;

        $lines = [];
        
        // Заголовок раунда - крупно, капсом, жирный
        $roundHeader = sprintf('РАУНД %d ИЗ %d', $currentRound, $totalRounds);
        $lines[] = sprintf('<b><strong>%s</strong></b>', $roundHeader);
        $lines[] = '';
        
        if ($formatter) {
            // Для прогресс-бара нужно показывать результат для каждого участника отдельно
            // Но так как сообщение отправляется обоим, показываем общий прогресс без привязки к пользователю
            $progressBar = $formatter->formatDuelProgress($currentRound, $totalRounds, $allRounds, null);
            $lines[] = $progressBar;
            $lines[] = '';
        }
        
        $lines[] = sprintf('⏱ Время на ответ: <b>%d сек.</b>', $timeLimit);
        $lines[] = '';
        $lines[] = sprintf('❓ <b>%s</b>', htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $lines[] = '';

        $buttons = [];
        $row = [];

        // Перемешиваем ответы в случайном порядке
        $answers = $question->answers->shuffle();

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

        // Создаём кастомный payload для каждого участника с правильным прогресс-баром
        $baseLines = $lines;
        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;
        
        $startTime = time();
        $hasImage = !empty($question->image_url);
        $messageIds = $this->broadcastToParticipants($duel, [
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup,
            'has_image' => $hasImage,
            'image_url' => $question->image_url,
        ], function ($payload, User $participant) use ($baseLines, $formatter, $currentRound, $totalRounds, $allRounds, $duel) {
            // Создаём кастомный прогресс-бар для каждого участника
            $customLines = $baseLines;
            if ($formatter !== null) {
                $userId = $participant->getKey();
                $progressBar = $formatter->formatDuelProgress($currentRound, $totalRounds, $allRounds, $userId);
                // Заменяем прогресс-бар (он на позиции после заголовка и пустой строки)
                $customLines[2] = $progressBar; // Индекс 2: после заголовка и пустой строки
            }
            
            $text = implode("\n", $customLines);
            $payload['text'] = $text;
            if (isset($payload['caption'])) {
                $payload['caption'] = $text;
            }
            
            return $payload;
        });

        // Запускаем фоновые скрипты для обновления таймера для каждого участника
        // Определяем basePath через рефлексию (трейт находится в bot/src/Presentation/Updates/Handlers/Concerns/)
        $reflection = new \ReflectionClass($this);
        $basePath = dirname($reflection->getFileName(), 5); // Поднимаемся на 5 уровней до bot/
        $scriptPath = $basePath . '/bin/duel_question_timer.php';
        $hasImage = !empty($question->image_url);
        $replyMarkupJson = json_encode($replyMarkup);

        foreach ($messageIds as $chatId => $messageId) {
            // Получаем текст для этого участника (нужно пересоздать с правильным прогресс-баром)
            $participant = $duel->initiator->telegram_id === $chatId ? $duel->initiator : $duel->opponent;
            $customLines = $baseLines;
            if ($formatter !== null && $participant !== null) {
                $userId = $participant->getKey();
                $progressBar = $formatter->formatDuelProgress($currentRound, $totalRounds, $allRounds, $userId);
                // Заменяем прогресс-бар (он на позиции после заголовка)
                $customLines[2] = $progressBar; // Индекс 2: после заголовка и пустой строки
            }
            $textForTimer = implode("\n", $customLines);
            
            // Проверяем существование скрипта
            if (!file_exists($scriptPath)) {
                $this->getLogger()->error('Скрипт таймера не найден', [
                    'script_path' => $scriptPath,
                    'duel_id' => $duel->getKey(),
                    'round_id' => $round->getKey(),
                ]);
                continue;
            }
            
            // Запускаем скрипт в фоне
            // Используем PHP CLI (не php-fpm!)
            // Пробуем разные варианты путей к PHP CLI
            $phpPath = 'php'; // По умолчанию используем php из PATH
            
            // Проверяем стандартные пути к PHP CLI
            $possiblePaths = [
                '/usr/bin/php',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
                '/usr/bin/php8.0',
                '/usr/bin/php7.4',
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && !is_dir($path)) {
                    // Проверяем, что это не php-fpm
                    $realPath = realpath($path);
                    if ($realPath !== false && strpos($realPath, 'fpm') === false) {
                        $phpPath = $path;
                        break;
                    }
                }
            }
            
            $logFile = $basePath . '/storage/logs/timer.log';
            
            // Убеждаемся, что директория для логов существует
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            
            $command = sprintf(
                'cd %s && nohup %s %s %d %d %d %d %d %s %s %d >> %s 2>&1 & echo $!',
                escapeshellarg($basePath),
                escapeshellarg($phpPath),
                escapeshellarg($scriptPath),
                $duel->getKey(),
                $round->getKey(),
                $chatId,
                $messageId,
                $startTime,
                escapeshellarg($textForTimer),
                escapeshellarg($replyMarkupJson),
                $hasImage ? 1 : 0,
                escapeshellarg($logFile)
            );
            
            $this->getLogger()->info('Запуск таймера дуэли', [
                'duel_id' => $duel->getKey(),
                'round_id' => $round->getKey(),
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'script_path' => $scriptPath,
                'php_path' => $phpPath,
                'command' => $command,
            ]);
            
            $processId = trim((string) shell_exec($command));
            
            if (!empty($processId) && is_numeric($processId)) {
                $this->getLogger()->debug('Таймер запущен', [
                    'process_id' => $processId,
                    'duel_id' => $duel->getKey(),
                    'round_id' => $round->getKey(),
                ]);
            } else {
                $this->getLogger()->warning('Не удалось получить PID процесса таймера', [
                    'duel_id' => $duel->getKey(),
                    'round_id' => $round->getKey(),
                    'output' => $processId,
                ]);
            }
        }
    }

    private function sendDuelRoundResult(Duel $duel, DuelRound $round): void
    {
        $round->loadMissing('question.answers');

        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;

        $initiatorSummary = $this->formatParticipantSummary($duel, $round, true);
        $opponentSummary = $this->formatParticipantSummary($duel, $round, false);

        // Показываем общий счёт матча, а не раунда
        $duel->loadMissing('rounds');
        $initiatorTotalScore = $duel->rounds->sum('initiator_score');
        $opponentTotalScore = $duel->rounds->sum('opponent_score');
        
        $scoreLine = sprintf(
            '⚔️ Счёт матча: <b>%d — %d</b>',
            $initiatorTotalScore,
            $opponentTotalScore
        );

        $lines = [];
        
        if ($formatter) {
            $lines[] = $formatter->header(sprintf('Итоги раунда %d', (int) $round->round_number), '📝');
        } else {
            $lines[] = sprintf('📝 <b>Итоги раунда %d</b>', (int) $round->round_number);
        }
        
        $lines[] = '';
        $lines = array_merge($lines, $initiatorSummary);
        $lines[] = '';
        $lines = array_merge($lines, $opponentSummary);
        $lines[] = '';
        $lines[] = $scoreLine;

        $payload = [
            'text' => implode("\n", $lines),
            'parse_mode' => 'HTML',
        ];

        $this->broadcastToParticipants($duel, $payload);
    }

    private function sendDuelFinalResult(Duel $duel, DuelResult $result): void
    {
        $duel->loadMissing('initiator', 'opponent');

        $initiatorScore = (int) $result->initiator_total_score;
        $opponentScore = (int) $result->opponent_total_score;

        $winnerName = 'Ничья';

        if ($result->winner_user_id !== null) {
            $winnerName = $this->formatUserName(
                $result->winner_user_id === $duel->initiator_user_id
                    ? $duel->initiator
                    : $duel->opponent
            );
        }

        // Получаем изменения рейтинга из метаданных
        $metadata = $result->metadata ?? [];
        $ratingChanges = $metadata['rating_changes'] ?? [
            'initiator_rating_change' => 0,
            'opponent_rating_change' => 0,
        ];
        $initiatorRatingChange = (int) ($ratingChanges['initiator_rating_change'] ?? 0);
        $opponentRatingChange = (int) ($ratingChanges['opponent_rating_change'] ?? 0);

        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;
        $client = $this->getTelegramClient();

        // Отправляем персональное сообщение каждому игроку
        foreach ([$duel->initiator, $duel->opponent] as $participant) {
            if (!$participant instanceof User) {
                continue;
            }

            $chatId = $participant->telegram_id;
            if ($chatId === null) {
                continue;
            }

            // Определяем изменение рейтинга для этого игрока
            $isInitiator = $participant->getKey() === $duel->initiator_user_id;
            $ratingChange = $isInitiator ? $initiatorRatingChange : $opponentRatingChange;

            $lines = [];
            
            if ($formatter) {
                $lines[] = $formatter->header('Дуэль завершена!', '🏁');
            } else {
                $lines[] = '🏁 <b>Дуэль завершена!</b>';
            }
            
            $lines[] = '';
            $lines[] = sprintf('⚔️ Итоговый счёт: <b>%d — %d</b>', $initiatorScore, $opponentScore);
            $lines[] = '';

            if ($result->winner_user_id === null) {
                $lines[] = '🤝 <b>Ничья!</b> Оба игрока показали отличный результат!';
            } else {
                $isWinner = $participant->getKey() === $result->winner_user_id;
                if ($isWinner) {
                    $lines[] = sprintf('🏆 <b>Победитель: %s</b>', $this->formatUserName($participant));
                    $lines[] = '🎉 Поздравляем с победой!';
                } else {
                    $lines[] = sprintf('🏆 <b>Победитель: %s</b>', $winnerName);
                }
            }
            
            // Добавляем изменение рейтинга
            $lines[] = '';
            if ($ratingChange > 0) {
                $lines[] = sprintf('⭐ Рейтинг: <b>+%d</b>', $ratingChange);
            } elseif ($ratingChange < 0) {
                $lines[] = sprintf('⭐ Рейтинг: <b>%d</b>', $ratingChange);
            } else {
                $lines[] = '⭐ Рейтинг: <b>0</b> (без изменений)';
            }
            
            if ($formatter) {
                $lines[] = '';
                $lines[] = $formatter->separator();
            }

            $payload = [
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getMainKeyboard(),
            ];

            try {
                $client->request('POST', 'sendMessage', [
                    'json' => array_merge([
                        'chat_id' => $chatId,
                    ], $payload),
                ]);
            } catch (\Throwable $e) {
                $this->getLogger()->error('Не удалось отправить финальный результат дуэли', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'duel_id' => $duel->getKey(),
                ]);
            }
        }
    }

    protected function sendDuelInvitationToUser(User $recipient, Duel $duel, User $initiator): void
    {
        $chatId = $recipient->telegram_id;

        if ($chatId === null) {
            $this->getLogger()->warning('Не удалось отправить приглашение: отсутствует telegram_id', [
                'duel_id' => $duel->getKey(),
                'recipient_id' => $recipient->getKey(),
            ]);

            return;
        }

        $lines = [
            '📨 <b>Приглашение в дуэль</b>',
            sprintf('%s вызывает тебя на дуэль 1 на 1.', $this->formatUserName($initiator)),
            '',
            'Выбери действие ниже:',
        ];

        $buttons = [
            [
                [
                    'text' => '✅ Принять',
                    'callback_data' => sprintf('duel-accept:%d', $duel->getKey()),
                ],
                [
                    'text' => '❌ Отказаться',
                    'callback_data' => sprintf('duel-reject:%d', $duel->getKey()),
                ],
            ],
        ];

        $this->getTelegramClient()->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $buttons,
                ],
            ],
        ]);
    }

    protected function broadcastDuelText(Duel $duel, string $text): void
    {
        $this->broadcastToParticipants($duel, [
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    private function broadcastToParticipants(Duel $duel, array $payload, ?callable $customizePayload = null): array
    {
        $client = $this->getTelegramClient();
        $messageIds = [];

        foreach ([$duel->initiator, $duel->opponent] as $participant) {
            if (!$participant instanceof User) {
                continue;
            }

            $chatId = $participant->telegram_id;

            if ($chatId === null) {
                continue;
            }

            $finalPayload = $payload;
            if ($customizePayload !== null) {
                $finalPayload = $customizePayload($payload, $participant);
            }

            try {
                // Если есть изображение, отправляем через sendPhoto
                if (!empty($finalPayload['has_image']) && !empty($finalPayload['image_url'])) {
                    $imagePath = $finalPayload['image_url'];
                    $isLocalFile = $this->isLocalFile($imagePath);
                    
                    if ($isLocalFile) {
                        // Локальный файл - используем multipart/form-data
                        $absolutePath = $this->resolveLocalPath($imagePath);
                        
                        if (!file_exists($absolutePath)) {
                            $this->getLogger()->error('Локальный файл изображения не найден', [
                                'path' => $absolutePath,
                                'chat_id' => $chatId,
                            ]);
                            // Отправляем как обычное текстовое сообщение
                            $response = $client->request('POST', 'sendMessage', [
                                'json' => $finalPayload + ['chat_id' => $chatId],
                            ]);
                        } else {
                            $multipart = [
                                ['name' => 'chat_id', 'contents' => (string) $chatId],
                                ['name' => 'photo', 'contents' => fopen($absolutePath, 'r')],
                                ['name' => 'caption', 'contents' => $finalPayload['text'] ?? ''],
                                ['name' => 'parse_mode', 'contents' => $finalPayload['parse_mode'] ?? 'HTML'],
                            ];
                            
                            if (isset($finalPayload['reply_markup'])) {
                                $multipart[] = [
                                    'name' => 'reply_markup',
                                    'contents' => json_encode($finalPayload['reply_markup']),
                                ];
                            }
                            
                            $response = $client->request('POST', 'sendPhoto', [
                                'multipart' => $multipart,
                            ]);
                        }
                    } else {
                        // URL - используем JSON
                        $photoPayload = [
                            'chat_id' => $chatId,
                            'photo' => $imagePath,
                            'caption' => $finalPayload['text'] ?? '',
                            'parse_mode' => $finalPayload['parse_mode'] ?? 'HTML',
                        ];
                        
                        if (isset($finalPayload['reply_markup'])) {
                            $photoPayload['reply_markup'] = $finalPayload['reply_markup'];
                        }
                        
                        $response = $client->request('POST', 'sendPhoto', [
                            'json' => $photoPayload,
                        ]);
                    }
                } else {
                    // Обычное текстовое сообщение
                    $response = $client->request('POST', 'sendMessage', [
                        'json' => $finalPayload + ['chat_id' => $chatId],
                    ]);
                }
                
                $responseBody = (string) $response->getBody();
                $responseData = json_decode($responseBody, true);
                if (isset($responseData['result']['message_id'])) {
                    $messageIds[$chatId] = (int) $responseData['result']['message_id'];
                }
            } catch (\Throwable $e) {
                $this->getLogger()->error('Ошибка отправки сообщения участнику дуэли', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'duel_id' => $duel->getKey(),
                ]);
            }
        }

        return $messageIds;
    }

    /**
     * Проверяет, является ли путь локальным файлом (не URL)
     */
    private function isLocalFile(string $path): bool
    {
        // Если путь начинается с http:// или https://, это URL
        if (preg_match('/^https?:\/\//', $path)) {
            return false;
        }
        
        // Если путь начинается с /, это абсолютный путь к локальному файлу
        if (strpos($path, '/') === 0) {
            return true;
        }
        
        // Если путь не содержит ://, это может быть относительный путь
        // Проверяем, существует ли файл относительно корня проекта
        return strpos($path, '://') === false;
    }
    
    /**
     * Преобразует путь к локальному файлу в абсолютный путь
     */
    private function resolveLocalPath(string $path): string
    {
        // Если путь уже абсолютный, возвращаем как есть
        if (strpos($path, '/') === 0) {
            return $path;
        }
        
        // Определяем basePath через рефлексию
        $reflection = new \ReflectionClass($this);
        $basePath = dirname($reflection->getFileName(), 5); // Поднимаемся на 5 уровней до bot/
        
        // Если путь начинается с storage/ или public/, используем их
        if (strpos($path, 'storage/') === 0 || strpos($path, 'public/') === 0) {
            return $basePath . '/' . $path;
        }
        
        // По умолчанию ищем в storage/images/
        return $basePath . '/storage/images/' . ltrim($path, '/');
    }

    /**
     * @return array<int, string>
     */
    private function formatParticipantSummary(Duel $duel, DuelRound $round, bool $forInitiator): array
    {
        $user = $forInitiator ? $duel->initiator : $duel->opponent;
        $payload = $forInitiator ? ($round->initiator_payload ?? []) : ($round->opponent_payload ?? []);
        $score = $forInitiator ? (int) $round->initiator_score : (int) $round->opponent_score;

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

        if (isset($payload['answer_id']) && $round->relationLoaded('question') && $round->question instanceof Question) {
            /** @var Answer|null $answer */
            $answer = $round->question->answers->firstWhere('id', $payload['answer_id']);
            if ($answer instanceof Answer) {
                $answerText = htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        $lines = [
            sprintf('%s — %s', $this->formatUserName($user), $status),
        ];

        if ($answerText !== null) {
            $lines[] = sprintf('Ответ: %s', $answerText);
        }

        if ($payload !== [] && isset($payload['time_elapsed'])) {
            $lines[] = sprintf('Время: %d сек.', (int) $payload['time_elapsed']);
        }

        return $lines;
    }

    private function formatUserName(?User $user): string
    {
        if (!$user instanceof User) {
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
}
