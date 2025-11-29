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

        $lines = [
            sprintf('⚔️ <b>Раунд %d</b>', (int) $round->round_number),
            sprintf('Время на ответ: %d сек.', $timeLimit),
            '',
            htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];

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

        $payload = [
            'text' => implode("\n", $lines),
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ];

        $this->broadcastToParticipants($duel, $payload);
    }

    private function sendDuelRoundResult(Duel $duel, DuelRound $round): void
    {
        $round->loadMissing('question.answers');

        $initiatorSummary = $this->formatParticipantSummary($duel, $round, true);
        $opponentSummary = $this->formatParticipantSummary($duel, $round, false);

        $scoreLine = sprintf(
            'Счёт раунда: %d — %d',
            (int) $round->initiator_score,
            (int) $round->opponent_score
        );

        $lines = array_merge(
            [sprintf('📝 <b>Итоги раунда %d</b>', (int) $round->round_number)],
            $initiatorSummary,
            [''],
            $opponentSummary,
            ['', $scoreLine]
        );

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

        $lines = [
            '🏁 <b>Дуэль завершена!</b>',
            sprintf('Итог: %d — %d', $initiatorScore, $opponentScore),
        ];

        if ($result->winner_user_id === null) {
            $lines[] = '🤝 Дуэль закончилась вничью.';
        } else {
            $lines[] = sprintf('🏆 Победитель: %s', $winnerName);
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

    private function broadcastToParticipants(Duel $duel, array $payload): void
    {
        $client = $this->getTelegramClient();

        foreach ([$duel->initiator, $duel->opponent] as $participant) {
            if (!$participant instanceof User) {
                continue;
            }

            $chatId = $participant->telegram_id;

            if ($chatId === null) {
                continue;
            }

            $client->request('POST', 'sendMessage', [
                'json' => $payload + ['chat_id' => $chatId],
            ]);
        }
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
