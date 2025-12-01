<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelResult;
use QuizBot\Domain\Model\DuelRound;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

class DuelService
{
    private Logger $logger;

    private QuestionSelector $questionSelector;

    public function __construct(Logger $logger, QuestionSelector $questionSelector)
    {
        $this->logger = $logger;
        $this->questionSelector = $questionSelector;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function createDuel(User $initiator, ?User $opponent = null, ?Category $category = null, array $settings = []): Duel
    {
        $duel = new Duel([
            'code' => $this->generateCode(),
            'initiator_user_id' => $initiator->getKey(),
            'opponent_user_id' => $opponent ? $opponent->getKey() : null,
            'category_id' => $category ? $category->getKey() : null,
            'rounds_to_win' => $settings['rounds_to_win'] ?? 5,
            'status' => $opponent === null ? 'waiting' : 'matched',
            'settings' => $settings,
            'matched_at' => $opponent === null ? null : Carbon::now(),
        ]);

        $duel->save();

        if ($opponent !== null) {
            $this->logger->info(sprintf(
                'Создана дуэль %s между %d и %d',
                $duel->code,
                $initiator->getKey(),
                $opponent->getKey()
            ));
        } else {
            $this->logger->info(sprintf(
                'Создана дуэль %s пользователем %d, ожидание соперника',
                $duel->code,
                $initiator->getKey()
            ));
        }

        return $duel;
    }

    public function findById(int $id): ?Duel
    {
        return Duel::query()->find($id);
    }

    public function findPendingInvitationForUser(User $initiator): ?Duel
    {
        return Duel::query()
            ->where('initiator_user_id', $initiator->getKey())
            ->where('status', 'waiting')
            ->orderByDesc('created_at')
            ->get()
            ->first(function (Duel $duel): bool {
                $settings = $duel->settings ?? [];

                return ($settings['awaiting_target'] ?? false) === true;
            });
    }

    public function markAwaitingTarget(Duel $duel): Duel
    {
        $settings = $duel->settings ?? [];
        $settings['awaiting_target'] = true;
        unset($settings['target_user_id'], $settings['target_username']);

        $duel->settings = $settings;
        $duel->save();

        return $duel->refresh();
    }

    public function attachTarget(Duel $duel, User $target): Duel
    {
        $settings = $duel->settings ?? [];
        $settings['target_user_id'] = $target->getKey();

        if (!empty($target->username)) {
            $settings['target_username'] = strtolower($target->username);
        } else {
            unset($settings['target_username']);
        }

        unset($settings['awaiting_target']);

        $duel->settings = $settings;
        $duel->save();

        return $duel->refresh();
    }

    public function acceptDuel(Duel $duel, User $opponent): Duel
    {
        if ($duel->status !== 'waiting') {
            throw new \RuntimeException('Нельзя присоединиться к дуэли: статус ' . $duel->status);
        }

        $settings = $duel->settings ?? [];
        $expectedId = isset($settings['target_user_id']) ? (int) $settings['target_user_id'] : null;
        $expectedUsername = isset($settings['target_username']) ? strtolower((string) $settings['target_username']) : null;

        if ($expectedId !== null && $expectedId !== $opponent->getKey()) {
            throw new \RuntimeException('Эта дуэль предназначена для другого игрока.');
        }

        if ($expectedId === null && $expectedUsername !== null) {
            $actualUsername = $opponent->username !== null ? strtolower($opponent->username) : null;

            if ($actualUsername === null || $actualUsername !== $expectedUsername) {
                throw new \RuntimeException('Эта дуэль предназначена для другого игрока.');
            }
        }

        $duel->opponent_user_id = $opponent->getKey();
        $duel->status = 'matched';
        $duel->matched_at = Carbon::now();
        unset($settings['target_username'], $settings['target_user_id'], $settings['awaiting_target'], $settings['matchmaking'], $settings['matchmaking_started_at']);
        $duel->settings = $settings;
        $duel->save();

        $this->logger->info(sprintf(
            'Пользователь %d присоединился к дуэли %s',
            $opponent->getKey(),
            $duel->code
        ));

        return $duel->refresh();
    }

    /**
     * @param array<int, array<string, mixed>> $roundConfigs
     */
    public function startDuel(Duel $duel, array $roundConfigs = []): Duel
    {
        if (!\in_array($duel->status, ['matched', 'waiting'], true)) {
            throw new \RuntimeException('Дуэль уже начата или завершена.');
        }

        $duel->status = 'in_progress';
        $duel->started_at = Carbon::now();
        $duel->save();

        $roundNumber = 1;
        $category = $duel->category;
        $roundCount = $roundConfigs ? count($roundConfigs) : ($duel->rounds_to_win * 2);
        $questionPool = $this->questionSelector->selectQuestions($category, $roundCount);

        foreach ($questionPool as $question) {
            $config = $roundConfigs[$roundNumber - 1] ?? [];

            $duel->rounds()->create([
                'question_id' => $question->getKey(),
                'round_number' => $roundNumber,
                'time_limit' => $config['time_limit'] ?? $question->time_limit ?? 30,
            ]);

            $roundNumber++;
        }

        $this->logger->info(sprintf('Дуэль %s стартовала', $duel->code));

        return $duel->refresh();
    }

    public function getCurrentRound(Duel $duel): ?DuelRound
    {
        return $duel->rounds()
            ->whereNull('closed_at')
            ->orderBy('round_number')
            ->first();
    }

    public function markRoundDispatched(DuelRound $round): DuelRound
    {
        if ($round->question_sent_at === null) {
            $round->question_sent_at = Carbon::now();
            $round->save();
        }

        return $round->refresh();
    }

    public function submitAnswer(DuelRound $round, User $user, int $answerId): DuelRound
    {
        $this->ensureRoundOpen($round);

        $round->loadMissing('question.answers', 'duel');

        $fieldPayload = $round->duel->initiator_user_id === $user->getKey()
            ? 'initiator_payload'
            : 'opponent_payload';

        $fieldScore = $round->duel->initiator_user_id === $user->getKey()
            ? 'initiator_score'
            : 'opponent_score';

        $currentPayload = $round->{$fieldPayload} ?? [];

        if ($this->isParticipantDone($currentPayload)) {
            throw new \RuntimeException('Ответ уже зафиксирован в этом раунде.');
        }

        $now = Carbon::now();

        if ($round->question_sent_at === null) {
            $round->question_sent_at = $now;
        }

        $timeLimit = max(1, (int) ($round->time_limit ?? 30));
        $elapsed = $round->question_sent_at->diffInSeconds($now);

        $payload = [
            'answered_at' => $now->toAtomString(),
            'time_elapsed' => $elapsed,
            'completed' => true,
        ];

        $question = $round->question;

        if ($question === null) {
            throw new \RuntimeException('Вопрос для раунда не найден.');
        }

        if ($elapsed > $timeLimit) {
            $payload += [
                'is_correct' => false,
                'answer_id' => null,
                'score' => 0,
                'reason' => 'timeout',
            ];
        } else {
            $answer = $question->answers->firstWhere('id', $answerId);

            if ($answer === null) {
                throw new \RuntimeException('Ответ не найден.');
            }

            $isCorrect = $answer->is_correct === true;
            $payload += [
                'is_correct' => $isCorrect,
                'answer_id' => $answerId,
                'score' => $isCorrect ? 1 : 0,
            ];
        }

        $round->{$fieldPayload} = $payload;
        $round->{$fieldScore} = $payload['score'];
        $round->save();

        // применяем тайм-ауты ко второму участнику, если время истекло
        $now = Carbon::now();
        $this->applyTimeoutIfNeeded($round, true, $now);
        $this->applyTimeoutIfNeeded($round, false, $now);

        $round->refresh();

        $this->maybeCompleteRound($round);

        if ($round->closed_at !== null) {
            $this->maybeCompleteDuel($round->duel);
        }

        return $round->refresh();
    }

    public function completeRound(DuelRound $round): DuelRound
    {
        $round->closed_at = Carbon::now();
        $round->save();

        $this->logger->debug(sprintf(
            'Раунд %d дуэли %s завершён',
            $round->round_number,
            $round->duel->code
        ));

        return $round->refresh();
    }

    public function finalizeDuel(Duel $duel): DuelResult
    {
        if ($duel->status === 'finished') {
            return $duel->result()->firstOrFail();
        }

        $duel->loadMissing('rounds', 'initiator.profile', 'opponent.profile');

        $initiatorScore = $duel->rounds->sum('initiator_score');
        $opponentScore = $duel->rounds->sum('opponent_score');
        $initiatorCorrect = $this->countCorrectAnswers($duel, true);
        $opponentCorrect = $this->countCorrectAnswers($duel, false);

        $winnerId = null;
        $resultStatus = 'draw';

        if ($initiatorScore > $opponentScore) {
            $winnerId = $duel->initiator_user_id;
            $resultStatus = 'initiator_win';
        } elseif ($opponentScore > $initiatorScore) {
            $winnerId = $duel->opponent_user_id;
            $resultStatus = 'opponent_win';
        }

        $result = new DuelResult([
            'winner_user_id' => $winnerId,
            'initiator_total_score' => $initiatorScore,
            'opponent_total_score' => $opponentScore,
            'initiator_correct' => $initiatorCorrect,
            'opponent_correct' => $opponentCorrect,
            'result' => $resultStatus,
            'metadata' => [],
        ]);

        $duel->result()->save($result);
        $duel->status = 'finished';
        $duel->finished_at = Carbon::now();
        $duel->save();

        $ratingChanges = $this->updateProfiles($duel, $resultStatus);

        // Сохраняем изменения рейтинга в метаданных результата
        $metadata = $result->metadata ?? [];
        $metadata['rating_changes'] = $ratingChanges;
        $result->metadata = $metadata;
        $result->save();

        $this->logger->info(sprintf('Дуэль %s завершена, результат %s', $duel->code, $resultStatus));

        return $result;
    }

    public function cancelWaitingDuel(Duel $duel, User $cancelledBy): Duel
    {
        if ($duel->status !== 'waiting') {
            return $duel;
        }

        $duel->status = 'cancelled';
        $duel->finished_at = Carbon::now();
        $settings = $duel->settings ?? [];
        unset($settings['matchmaking'], $settings['matchmaking_started_at']);
        $duel->settings = $settings;
        $duel->save();

        $this->logger->info(sprintf(
            'Дуэль %s отменена пользователем %d',
            $duel->code,
            $cancelledBy->getKey()
        ));

        return $duel->refresh();
    }

    public function createMatchmakingTicket(User $initiator): Duel
    {
        return $this->createDuel($initiator, null, null, [
            'matchmaking' => true,
            'matchmaking_started_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function findUserMatchmakingTicket(User $user, int $ttlSeconds = 30): ?Duel
    {
        $threshold = Carbon::now()->subSeconds($ttlSeconds);

        return Duel::query()
            ->where('status', 'waiting')
            ->where('initiator_user_id', $user->getKey())
            ->whereNull('opponent_user_id')
            ->where('created_at', '>=', $threshold)
            ->orderByDesc('created_at')
            ->get()
            ->first(function (Duel $duel): bool {
                return $this->isMatchmaking($duel);
            });
    }

    public function findAvailableMatchmakingTicket(User $user, int $ttlSeconds = 30): ?Duel
    {
        $threshold = Carbon::now()->subSeconds($ttlSeconds);

        return Duel::query()
            ->where('status', 'waiting')
            ->where('initiator_user_id', '!=', $user->getKey())
            ->whereNull('opponent_user_id')
            ->where('created_at', '>=', $threshold)
            ->orderBy('created_at')
            ->get()
            ->first(function (Duel $duel) use ($user): bool {
                if (!$this->isMatchmaking($duel)) {
                    return false;
                }

                return $duel->initiator_user_id !== $user->getKey();
            });
    }

    public function isMatchmaking(Duel $duel): bool
    {
        $settings = $duel->settings ?? [];

        return isset($settings['matchmaking']) && $settings['matchmaking'] === true;
    }

    public function cancelAllActiveDuels(): int
    {
        $activeStatuses = ['waiting', 'matched', 'in_progress'];
        $duels = Duel::query()
            ->whereIn('status', $activeStatuses)
            ->get();

        if ($duels->isEmpty()) {
            return 0;
        }

        $now = Carbon::now();
        $cancelled = 0;

        foreach ($duels as $duel) {
            $duel->status = 'cancelled';
            $duel->finished_at = $now;
            $duel->save();

            $cancelled++;

            $this->logger->info(sprintf(
                'Дуэль %s принудительно отменена массовой операцией',
                $duel->code
            ));
        }

        return $cancelled;
    }

    public function findActiveDuelForUser(User $user): ?Duel
    {
        return Duel::query()
            ->where(function ($query) use ($user): void {
                $query->where('initiator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->whereIn('status', ['waiting', 'matched', 'in_progress'])
            ->latest()
            ->first();
    }

    /**
     * @return EloquentCollection<int, Duel>
     */
    public function getRecentDuels(User $user, int $limit = 5): EloquentCollection
    {
        return Duel::query()
            ->where(function ($query) use ($user): void {
                $query->where('initiator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->orderByDesc('updated_at')
            ->limit(max(1, $limit))
            ->with(['initiator', 'opponent', 'result'])
            ->get();
    }

    private function ensureRoundOpen(DuelRound $round): void
    {
        if ($round->closed_at !== null) {
            throw new \RuntimeException('Раунд уже завершён.');
        }
    }

    private function generateCode(): string
    {
        return strtoupper(Str::random(8));
    }

    private function countCorrectAnswers(Duel $duel, bool $isInitiator): int
    {
        $counter = 0;

        foreach ($duel->rounds as $round) {
            $payload = $isInitiator ? $round->initiator_payload : $round->opponent_payload;

            if (isset($payload['is_correct']) && $payload['is_correct'] === true) {
                $counter++;
            }
        }

        return $counter;
    }

    public function maybeCompleteRound(DuelRound $round): void
    {
        $now = Carbon::now();
        $updated = false;

        $updated = $this->applyTimeoutIfNeeded($round, true, $now) || $updated;
        $updated = $this->applyTimeoutIfNeeded($round, false, $now) || $updated;

        if ($updated) {
            $round->refresh();
        }

        $initiatorDone = $this->isParticipantDone($round->initiator_payload ?? []);
        $opponentDone = $this->isParticipantDone($round->opponent_payload ?? []);

        if ($initiatorDone && $opponentDone) {
            $this->completeRound($round);
        }
    }

    public function maybeCompleteDuel(Duel $duel): void
    {
        $duel->loadMissing('rounds');

        $roundsToWin = max(1, (int) $duel->rounds_to_win);

        $initiatorWins = 0;
        $opponentWins = 0;

        foreach ($duel->rounds as $round) {
            if ($round->closed_at === null) {
                return;
            }

            if ($round->initiator_score > $round->opponent_score) {
                $initiatorWins++;
            } elseif ($round->opponent_score > $round->initiator_score) {
                $opponentWins++;
            }
        }

        if ($initiatorWins >= $roundsToWin || $opponentWins >= $roundsToWin || $duel->rounds->every(fn (DuelRound $r) => $r->closed_at !== null)) {
            $this->finalizeDuel($duel);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isParticipantDone(array $payload): bool
    {
        if (isset($payload['completed']) && $payload['completed'] === true) {
            return true;
        }

        return isset($payload['answer_id'], $payload['score']) || (($payload['reason'] ?? null) === 'timeout');
    }

    public function applyTimeoutIfNeeded(DuelRound $round, bool $forInitiator, Carbon $now): bool
    {
        if ($round->question_sent_at === null) {
            return false;
        }

        $timeLimit = $round->time_limit ?? 30;

        if ($timeLimit <= 0) {
            return false;
        }

        if ($round->question_sent_at->diffInSeconds($now) <= $timeLimit) {
            return false;
        }

        $fieldPayload = $forInitiator ? 'initiator_payload' : 'opponent_payload';
        $fieldScore = $forInitiator ? 'initiator_score' : 'opponent_score';

        $current = $round->{$fieldPayload} ?? [];

        if ($this->isParticipantDone($current)) {
            return false;
        }

        $round->{$fieldPayload} = [
            'completed' => true,
            'is_correct' => false,
            'answer_id' => null,
            'score' => 0,
            'reason' => 'timeout',
            'answered_at' => $now->toAtomString(),
            'time_elapsed' => $round->question_sent_at->diffInSeconds($now),
        ];

        $round->{$fieldScore} = 0;
        $round->save();

        return true;
    }

    private function updateProfiles(Duel $duel, string $resultStatus): array
    {
        $initiator = $duel->initiator;
        $opponent = $duel->opponent;

        if (!$initiator instanceof User || !$opponent instanceof User) {
            return ['initiator_rating_change' => 0, 'opponent_rating_change' => 0];
        }

        $initiator = $initiator->fresh(['profile']);
        $opponent = $opponent->fresh(['profile']);

        if (!$initiator?->profile instanceof UserProfile || !$opponent?->profile instanceof UserProfile) {
            return ['initiator_rating_change' => 0, 'opponent_rating_change' => 0];
        }

        $initiatorProfile = $initiator->profile;
        $opponentProfile = $opponent->profile;

        // Получаем текущие рейтинги для расчета изменения
        $initiatorRating = (int) $initiatorProfile->rating;
        $opponentRating = (int) $opponentProfile->rating;

        // Базовая система рейтинга: фиксированные значения
        // Можно улучшить, учитывая разницу рейтингов
        $ratingChange = $this->calculateRatingChange($initiatorRating, $opponentRating);

        $initiatorRatingChange = 0;
        $opponentRatingChange = 0;

        switch ($resultStatus) {
            case 'initiator_win':
                $initiatorProfile->duel_wins++;
                $initiatorRatingChange = $ratingChange;
                $initiatorProfile->rating = max(0, $initiatorRating + $ratingChange);
                $initiatorProfile->streak_days = (int) $initiatorProfile->streak_days + 1;

                $opponentProfile->duel_losses++;
                $opponentRatingChange = -$ratingChange;
                $opponentProfile->rating = max(0, $opponentRating - $ratingChange);
                $opponentProfile->streak_days = 0;
                break;
            case 'opponent_win':
                $opponentProfile->duel_wins++;
                $opponentRatingChange = $ratingChange;
                $opponentProfile->rating = max(0, $opponentRating + $ratingChange);
                $opponentProfile->streak_days = (int) $opponentProfile->streak_days + 1;

                $initiatorProfile->duel_losses++;
                $initiatorRatingChange = -$ratingChange;
                $initiatorProfile->rating = max(0, $initiatorRating - $ratingChange);
                $initiatorProfile->streak_days = 0;
                break;
            default:
                // Ничья: рейтинг не меняется
                $initiatorProfile->duel_draws++;
                $opponentProfile->duel_draws++;
                break;
        }

        $initiatorProfile->save();
        $opponentProfile->save();

        return [
            'initiator_rating_change' => $initiatorRatingChange,
            'opponent_rating_change' => $opponentRatingChange,
        ];
    }

    /**
     * Рассчитывает изменение рейтинга на основе текущих рейтингов игроков
     * Базовая система: фиксированное изменение, но можно учитывать разницу
     */
    private function calculateRatingChange(int $playerRating, int $opponentRating): int
    {
        $ratingDiff = $playerRating - $opponentRating;
        
        // Базовое изменение: 10 очков
        $baseChange = 10;
        
        // Если игрок сильнее соперника более чем на 200 очков, получает меньше за победу
        // Если игрок слабее соперника более чем на 200 очков, получает больше за победу
        if ($ratingDiff > 200) {
            // Сильный игрок побеждает слабого: меньше очков
            $baseChange = max(5, $baseChange - 2);
        } elseif ($ratingDiff < -200) {
            // Слабый игрок побеждает сильного: больше очков
            $baseChange = min(15, $baseChange + 2);
        }
        
        return $baseChange;
    }
}

