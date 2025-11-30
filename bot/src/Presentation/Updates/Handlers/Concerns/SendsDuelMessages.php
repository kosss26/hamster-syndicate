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

        foreach ($question->answers as $index => $answer) {
            $row[] = [
                'text' => htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'callback_data' => sprintf('duel-answer:%d:%d:%d', $duel->getKey(), $round->getKey(), $answer->getKey()),
            ];

            if (count($row) === 2 || $index === count($question->answers) - 1) {
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
        $messageIds = $this->broadcastToParticipants($duel, [
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup,
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
            
            return $payload;
        });

        // Запускаем фоновые скрипты для обновления таймера для каждого участника
        // Определяем basePath через рефлексию
        $reflection = new \ReflectionClass($this);
        $basePath = dirname($reflection->getFileName(), 4);
        $scriptPath = $basePath . '/bin/duel_question_timer.php';
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
            // Используем абсолютный путь к PHP и полный путь к скрипту
            $phpPath = PHP_BINARY ?: 'php';
            $logFile = $basePath . '/storage/logs/timer.log';
            $command = sprintf(
                'cd %s && %s %s %d %d %d %d %d %s %s >> %s 2>&1 &',
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
                escapeshellarg($logFile)
            );
            
            $this->getLogger()->info('Запуск таймера дуэли', [
                'duel_id' => $duel->getKey(),
                'round_id' => $round->getKey(),
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'script_path' => $scriptPath,
                'php_path' => $phpPath,
            ]);
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0 && !empty($output)) {
                $this->getLogger()->warning('Предупреждение при запуске таймера', [
                    'return_var' => $returnVar,
                    'output' => implode("\n", $output),
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

        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;

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
            $lines[] = sprintf('🏆 <b>Победитель: %s</b>', $winnerName);
            $lines[] = '🎉 Поздравляем с победой!';
        }
        
        if ($formatter) {
            $lines[] = '';
            $lines[] = $formatter->separator();
        }

        $payload = [
            'text' => implode("\n", $lines),
            'parse_mode' => 'HTML',
        ];

        $this->broadcastToParticipants($duel, $payload);
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
                $response = $client->request('POST', 'sendMessage', [
                    'json' => $finalPayload + ['chat_id' => $chatId],
                ]);
                
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
