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

        // Используем MessageFormatter если доступен
        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;

        $lines = [];
        
        if ($formatter) {
            $progressBar = $formatter->formatDuelProgress($currentRound, $totalRounds);
            $lines[] = $progressBar;
            $lines[] = '';
        } else {
            $lines[] = sprintf('⚔️ <b>Раунд %d/%d</b>', $currentRound, $totalRounds);
        }
        
        $lines[] = sprintf('⏱ Время на ответ: <b>%d сек.</b>', $timeLimit);
        $lines[] = '━━━━━━━━━━━━━━━━━━';
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

        $text = implode("\n", $lines);
        $replyMarkup = [
            'inline_keyboard' => $buttons,
        ];

        $payload = [
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup,
        ];

        $messageIds = $this->broadcastToParticipants($duel, $payload);

        // Запускаем фоновые скрипты для обновления таймера для каждого участника
        $basePath = dirname(__DIR__, 4);
        $scriptPath = $basePath . '/bin/duel_question_timer.php';
        $startTime = time();
        $replyMarkupJson = json_encode($replyMarkup);

        foreach ($messageIds as $chatId => $messageId) {
            // Запускаем скрипт в фоне
            $command = sprintf(
                'php %s %d %d %d %d %d %s %s > /dev/null 2>&1 &',
                escapeshellarg($scriptPath),
                $duel->getKey(),
                $round->getKey(),
                $chatId,
                $messageId,
                $startTime,
                escapeshellarg($text),
                escapeshellarg($replyMarkupJson)
            );
            
            exec($command);
        }
    }

    private function sendDuelRoundResult(Duel $duel, DuelRound $round): void
    {
        $round->loadMissing('question.answers');

        $formatter = method_exists($this, 'getMessageFormatter') ? $this->getMessageFormatter() : null;

        $initiatorSummary = $this->formatParticipantSummary($duel, $round, true);
        $opponentSummary = $this->formatParticipantSummary($duel, $round, false);

        $scoreLine = sprintf(
            '⚔️ Счёт раунда: <b>%d — %d</b>',
            (int) $round->initiator_score,
            (int) $round->opponent_score
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

    private function broadcastToParticipants(Duel $duel, array $payload): array
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

            try {
                $response = $client->request('POST', 'sendMessage', [
                    'json' => $payload + ['chat_id' => $chatId],
                ]);
                
                $responseData = json_decode($response->getBody()->getContents(), true);
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
            sprintf('Очки: %d', $score),
        ];

        if ($answerText !== null) {
            $lines[] = sprintf('Ответ: %s', $answerText);
        }

        if ($payload !== [] && isset($payload['time_elapsed'])) {
            $lines[] = sprintf('Время: %d с', (int) $payload['time_elapsed']);
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
