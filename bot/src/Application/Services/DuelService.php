<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelGhostPlay;
use QuizBot\Domain\Model\DuelGhostSnapshot;
use QuizBot\Domain\Model\DuelResult;
use QuizBot\Domain\Model\DuelRound;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

class DuelService
{
    private const GHOST_SEARCH_TIMEOUT_SECONDS = 30;
    private const GHOST_RATING_COEFFICIENT = 0.5;

    private Logger $logger;

    private QuestionSelector $questionSelector;

    private ?ReferralService $referralService;

    private ?StatisticsService $statisticsService;

    public function __construct(
        Logger $logger,
        QuestionSelector $questionSelector,
        ?ReferralService $referralService = null,
        ?StatisticsService $statisticsService = null
    ) {
        $this->logger = $logger;
        $this->questionSelector = $questionSelector;
        $this->referralService = $referralService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function createDuel(User $initiator, ?User $opponent = null, ?Category $category = null, array $settings = []): Duel
    {
        $duel = null;
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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

            try {
                $duel->save();
                break;
            } catch (QueryException $e) {
                if (!$this->isDuplicateDuelCodeError($e) || $attempt === $maxAttempts) {
                    throw $e;
                }

                $this->logger->warning('Коллизия кода дуэли, повторная генерация', [
                    'attempt' => $attempt,
                    'initiator_user_id' => $initiator->getKey(),
                ]);
            }
        }

        if (!$duel instanceof Duel || !$duel->exists) {
            throw new \RuntimeException('Не удалось создать дуэль после нескольких попыток.');
        }

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
        $explicitQuestionIds = array_values(array_filter(array_map(
            static fn (array $config): int => (int) ($config['question_id'] ?? 0),
            $roundConfigs
        )));

        if ($roundConfigs !== [] && count($explicitQuestionIds) === $roundCount) {
            /** @var Collection<int, Question> $questionMap */
            $questionMap = Question::query()
                ->whereIn('id', $explicitQuestionIds)
                ->get()
                ->keyBy('id');

            foreach ($roundConfigs as $config) {
                $questionId = (int) ($config['question_id'] ?? 0);
                /** @var Question|null $question */
                $question = $questionMap->get($questionId);
                if (!$question) {
                    throw new \RuntimeException('Не найден вопрос для призрачной дуэли: ' . $questionId);
                }

                $initiatorPayload = is_array($config['initiator_payload'] ?? null) ? $config['initiator_payload'] : null;
                $opponentPayload = is_array($config['opponent_payload'] ?? null) ? $config['opponent_payload'] : null;

                $duel->rounds()->create([
                    'question_id' => $question->getKey(),
                    'round_number' => $roundNumber,
                    'time_limit' => (int) ($config['time_limit'] ?? $question->time_limit ?? 30),
                    'initiator_payload' => $initiatorPayload,
                    'opponent_payload' => $opponentPayload,
                    'initiator_score' => isset($initiatorPayload['score']) ? (int) $initiatorPayload['score'] : 0,
                    'opponent_score' => isset($opponentPayload['score']) ? (int) $opponentPayload['score'] : 0,
                ]);

                $roundNumber++;
            }
        } else {
            $questionPool = $this->questionSelector->selectQuestions($category, $roundCount);

            foreach ($questionPool as $question) {
                $config = $roundConfigs[$roundNumber - 1] ?? [];

                $duel->rounds()->create([
                    'question_id' => $question->getKey(),
                    'round_number' => $roundNumber,
                    'time_limit' => $config['time_limit'] ?? $question->time_limit ?? 30,
                    'initiator_payload' => is_array($config['initiator_payload'] ?? null) ? $config['initiator_payload'] : null,
                    'opponent_payload' => is_array($config['opponent_payload'] ?? null) ? $config['opponent_payload'] : null,
                    'initiator_score' => isset($config['initiator_payload']['score']) ? (int) $config['initiator_payload']['score'] : 0,
                    'opponent_score' => isset($config['opponent_payload']['score']) ? (int) $config['opponent_payload']['score'] : 0,
                ]);

                $roundNumber++;
            }
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

    public function submitAnswer(DuelRound $round, User $user, ?int $answerId): DuelRound
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

        // Таймаут: если answerId = null или время истекло
        if ($answerId === null || $elapsed > $timeLimit) {
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
        $isGhostMatch = $this->isGhostMatch($duel);

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

        // Обновляем статистику и проверяем достижения
        $achievementUnlocks = [
            'initiator' => [],
            'opponent' => [],
        ];
        if ($this->statisticsService) {
            try {
                // Для инициатора
                $isInitiatorWin = $resultStatus === 'initiator_win';
                $achievementUnlocks['initiator'] = $this->statisticsService->recordDuelResult($duel->initiator, $isInitiatorWin);

                // Для оппонента (если есть, и это не призрак)
                if ($duel->opponent && !$isGhostMatch) {
                    $isOpponentWin = $resultStatus === 'opponent_win';
                    $achievementUnlocks['opponent'] = $this->statisticsService->recordDuelResult($duel->opponent, $isOpponentWin);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Ошибка обновления статистики дуэли: ' . $e->getMessage());
            }
        }

        $metadata = $result->metadata ?? [];
        $metadata['achievement_unlocks'] = $achievementUnlocks;
        $result->metadata = $metadata;
        $result->save();

        // Проверяем активацию рефералов для обоих участников
        $this->checkReferralActivation($duel->initiator);
        if ($duel->opponent && !$isGhostMatch) {
            $this->checkReferralActivation($duel->opponent);
        }

        // Сохраняем слепки реальных матчей для fallback "дуэль с призраком".
        if (!$isGhostMatch) {
            $this->createGhostSnapshotsForDuel($duel);
        }

        return $result;
    }

    /**
     * Проверяет и активирует реферала если нужно
     */
    private function checkReferralActivation(User $user): void
    {
        if ($this->referralService === null) {
            return;
        }

        try {
            $this->referralService->checkAndActivateReferral($user);
        } catch (\Throwable $e) {
            $this->logger->debug('Не удалось проверить активацию реферала', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
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

    public function shouldUseGhostFallback(Duel $duel, int $timeoutSeconds = self::GHOST_SEARCH_TIMEOUT_SECONDS): bool
    {
        if ($duel->status !== 'waiting' || !$this->isMatchmaking($duel) || $duel->opponent_user_id !== null) {
            return false;
        }

        $ageSeconds = $duel->created_at ? $duel->created_at->diffInSeconds(Carbon::now()) : 0;
        return $ageSeconds >= max(1, $timeoutSeconds);
    }

    public function assignGhostOpponentForMatchmaking(Duel $duel, User $seeker): ?Duel
    {
        if (!$this->shouldUseGhostFallback($duel) || (int) $duel->initiator_user_id !== (int) $seeker->getKey()) {
            return null;
        }

        $snapshot = $this->selectGhostSnapshotForUser($seeker);
        if (!$snapshot) {
            $this->logger->info('Не найден подходящий призрак для fallback', [
                'duel_id' => $duel->getKey(),
                'user_id' => $seeker->getKey(),
            ]);
            return null;
        }

        $sourceUser = User::query()->find((int) $snapshot->source_user_id);
        if (!$sourceUser) {
            $this->logger->warning('Snapshot без source user пропущен', [
                'snapshot_id' => $snapshot->getKey(),
                'source_user_id' => $snapshot->source_user_id,
            ]);
            return null;
        }

        if ((int) $sourceUser->getKey() === (int) $seeker->getKey()) {
            return null;
        }

        $duel = $this->acceptDuel($duel, $sourceUser);
        $settings = is_array($duel->settings) ? $duel->settings : [];
        $settings['match_type'] = 'ghost';
        $settings['ghost_mode'] = true;
        $settings['ghost_snapshot_id'] = (int) $snapshot->getKey();
        $settings['ghost_source_user_id'] = (int) $sourceUser->getKey();
        $settings['ghost_source_rating'] = (int) $snapshot->source_rating;
        $settings['ghost_rating_factor'] = self::GHOST_RATING_COEFFICIENT;
        $settings['ticket_charged_opponent'] = true;
        $settings['ghost_round_configs'] = $this->buildGhostRoundConfigsFromSnapshot($snapshot);
        $duel->settings = $settings;
        $duel->save();

        DuelGhostPlay::query()->firstOrCreate(
            [
                'user_id' => (int) $seeker->getKey(),
                'snapshot_id' => (int) $snapshot->getKey(),
            ],
            [
                'duel_id' => (int) $duel->getKey(),
                'created_at' => Carbon::now(),
            ]
        );

        $this->logger->info('Matchmaking fallback на призрака активирован', [
            'duel_id' => $duel->getKey(),
            'user_id' => $seeker->getKey(),
            'snapshot_id' => $snapshot->getKey(),
            'ghost_user_id' => $sourceUser->getKey(),
        ]);

        return $duel->refresh();
    }

    public function hasGhostSnapshotForUser(User $user): bool
    {
        return $this->selectGhostSnapshotForUser($user) instanceof DuelGhostSnapshot;
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

    /**
     * Отменяет все зависшие matchmaking-дуэли старше указанного TTL
     */
    public function cleanupStaleMatchmakingDuels(int $ttlSeconds = 60): int
    {
        $threshold = Carbon::now()->subSeconds($ttlSeconds);
        $now = Carbon::now();
        
        $staleDuels = Duel::query()
            ->where('status', 'waiting')
            ->whereNull('opponent_user_id')
            ->where('created_at', '<', $threshold)
            ->get()
            ->filter(function (Duel $duel): bool {
                return $this->isMatchmaking($duel);
            });

        if ($staleDuels->isEmpty()) {
            return 0;
        }

        $cancelled = 0;

        foreach ($staleDuels as $duel) {
            $settings = $duel->settings ?? [];
            unset($settings['matchmaking'], $settings['matchmaking_started_at']);
            
            $duel->status = 'cancelled';
            $duel->finished_at = $now;
            $duel->settings = $settings;
            $duel->save();

            $cancelled++;

            $this->logger->info(sprintf(
                'Зависшая matchmaking-дуэль %s отменена (возраст: %d сек)',
                $duel->code,
                $duel->created_at->diffInSeconds($now)
            ));
        }

        return $cancelled;
    }

    public function findActiveDuelForUser(User $user, bool $autoCleanup = true): ?Duel
    {
        $matchmakingTtl = 60; // секунд - TTL для matchmaking дуэлей
        $threshold = Carbon::now()->subSeconds($matchmakingTtl);

        $duel = Duel::query()
            ->where(function ($query) use ($user): void {
                $query->where('initiator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->whereIn('status', ['waiting', 'matched', 'in_progress'])
            ->latest()
            ->first();

        if ($duel === null) {
            return null;
        }

        // Если это старая waiting matchmaking-дуэль - автоматически отменяем
        if ($autoCleanup && $duel->status === 'waiting' && $this->isMatchmaking($duel)) {
            if ($duel->created_at < $threshold) {
                $this->logger->info(sprintf(
                    'Автоматическая отмена зависшей matchmaking-дуэли %s (создана %s)',
                    $duel->code,
                    $duel->created_at->toDateTimeString()
                ));
                
                $initiator = $duel->initiator;
                if ($initiator instanceof User) {
                    $this->cancelWaitingDuel($duel, $initiator);
                } else {
                    $duel->status = 'cancelled';
                    $duel->finished_at = Carbon::now();
                    $duel->save();
                }
                
                return null;
            }
        }

        return $duel;
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
        // 5-значный цифровой код (00000-99999) с проверкой уникальности.
        // Несколько попыток защищают от редких коллизий при высокой нагрузке.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $exists = Duel::query()->where('code', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать уникальный код дуэли.');
    }

    private function isDuplicateDuelCodeError(QueryException $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        if (strpos($message, 'duels.code') !== false && strpos($message, 'unique') !== false) {
            return true;
        }

        if (strpos($message, 'duplicate entry') !== false && strpos($message, 'duels') !== false) {
            return true;
        }

        return false;
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

    private function selectGhostSnapshotForUser(User $user): ?DuelGhostSnapshot
    {
        $user = $user->fresh(['profile']);
        $targetRating = 1000;
        if ($user && $user->profile) {
            $targetRating = (int) $user->profile->rating;
        }
        $ratingMin = max(0, $targetRating - 250);
        $ratingMax = $targetRating + 250;

        $baseQuery = DuelGhostSnapshot::query()
            ->leftJoin('duel_ghost_plays', function ($join) use ($user): void {
                $join->on('duel_ghost_plays.snapshot_id', '=', 'duel_ghost_snapshots.id')
                    ->where('duel_ghost_plays.user_id', '=', (int) $user->getKey());
            })
            ->whereNull('duel_ghost_plays.id')
            ->where('duel_ghost_snapshots.source_user_id', '!=', (int) $user->getKey())
            ->orderByDesc('duel_ghost_snapshots.quality_score')
            ->orderByRaw('ABS(duel_ghost_snapshots.source_rating - ?) ASC', [$targetRating])
            ->orderByDesc('duel_ghost_snapshots.created_at')
            ->select('duel_ghost_snapshots.*');

        $strict = (clone $baseQuery)
            ->whereBetween('duel_ghost_snapshots.source_rating', [$ratingMin, $ratingMax])
            ->first();

        if ($strict instanceof DuelGhostSnapshot) {
            return $strict;
        }

        return (clone $baseQuery)->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGhostRoundConfigsFromSnapshot(DuelGhostSnapshot $snapshot): array
    {
        $payload = is_array($snapshot->rounds_payload) ? $snapshot->rounds_payload : [];
        $rounds = is_array($payload['rounds'] ?? null) ? $payload['rounds'] : [];
        $configs = [];

        foreach ($rounds as $round) {
            if (!is_array($round)) {
                continue;
            }

            $questionId = (int) ($round['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $ghostPayload = $this->normalizeGhostRoundPayload($round);
            $configs[] = [
                'question_id' => $questionId,
                'time_limit' => max(10, (int) ($round['time_limit'] ?? 30)),
                'opponent_payload' => $ghostPayload,
            ];
        }

        return $configs;
    }

    /**
     * @param array<string, mixed> $round
     * @return array<string, mixed>
     */
    private function normalizeGhostRoundPayload(array $round): array
    {
        $isCorrect = (bool) ($round['is_correct'] ?? false);
        $reason = isset($round['reason']) ? (string) $round['reason'] : null;
        $timeElapsed = max(0, (int) ($round['time_elapsed'] ?? 0));

        return [
            'completed' => true,
            'is_correct' => $isCorrect,
            'answer_id' => isset($round['answer_id']) ? (int) $round['answer_id'] : null,
            'score' => $isCorrect ? 1 : 0,
            'reason' => $reason,
            'time_elapsed' => $timeElapsed,
            'answered_at' => isset($round['answered_at']) ? (string) $round['answered_at'] : Carbon::now()->toAtomString(),
        ];
    }

    private function isGhostMatch(Duel $duel): bool
    {
        $settings = is_array($duel->settings) ? $duel->settings : [];
        return $this->isGhostModeSettings($settings);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isGhostModeSettings(array $settings): bool
    {
        return (($settings['ghost_mode'] ?? false) === true) || (($settings['match_type'] ?? '') === 'ghost');
    }

    private function createGhostSnapshotsForDuel(Duel $duel): void
    {
        if ($this->isGhostMatch($duel)) {
            return;
        }

        $duel->loadMissing('rounds.question', 'initiator.profile', 'opponent.profile');
        if (!$duel->opponent) {
            return;
        }

        $participants = [
            [
                'user' => $duel->initiator,
                'is_initiator' => true,
            ],
            [
                'user' => $duel->opponent,
                'is_initiator' => false,
            ],
        ];

        foreach ($participants as $participant) {
            /** @var User|null $participantUser */
            $participantUser = $participant['user'];
            if (!$participantUser) {
                continue;
            }

            $rounds = [];
            $validRounds = 0;
            foreach ($duel->rounds as $round) {
                $payload = $participant['is_initiator'] ? ($round->initiator_payload ?? []) : ($round->opponent_payload ?? []);
                if (!is_array($payload) || empty($payload['completed'])) {
                    continue;
                }

                $questionId = (int) $round->question_id;
                if ($questionId <= 0) {
                    continue;
                }

                $rounds[] = [
                    'round_number' => (int) $round->round_number,
                    'question_id' => $questionId,
                    'time_limit' => max(10, (int) ($round->time_limit ?? 30)),
                    'answer_id' => isset($payload['answer_id']) && $payload['answer_id'] !== null ? (int) $payload['answer_id'] : null,
                    'is_correct' => (bool) ($payload['is_correct'] ?? false),
                    'reason' => isset($payload['reason']) ? (string) $payload['reason'] : null,
                    'time_elapsed' => max(0, (int) ($payload['time_elapsed'] ?? 0)),
                    'answered_at' => isset($payload['answered_at']) ? (string) $payload['answered_at'] : null,
                ];
                $validRounds++;
            }

            if ($validRounds < 3) {
                continue;
            }

            $qualityScore = (int) round($validRounds * 10);
            DuelGhostSnapshot::query()->create([
                'source_duel_id' => (int) $duel->getKey(),
                'source_user_id' => (int) $participantUser->getKey(),
                'source_rating' => $participantUser->profile ? (int) $participantUser->profile->rating : 1000,
                'question_count' => $validRounds,
                'quality_score' => $qualityScore,
                'rounds_payload' => ['rounds' => $rounds],
                'created_at' => Carbon::now(),
            ]);
        }
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

        if (!($initiator->profile instanceof UserProfile) || !($opponent->profile instanceof UserProfile)) {
            return ['initiator_rating_change' => 0, 'opponent_rating_change' => 0];
        }

        $initiatorProfile = $initiator->profile;
        $opponentProfile = $opponent->profile;
        $isGhostMatch = $this->isGhostMatch($duel);

        // Получаем текущие рейтинги для расчета изменения
        $initiatorRating = (int) $initiatorProfile->rating;
        $opponentRating = (int) $opponentProfile->rating;
        if ($isGhostMatch) {
            $settings = is_array($duel->settings) ? $duel->settings : [];
            if (isset($settings['ghost_source_rating'])) {
                $opponentRating = max(0, (int) $settings['ghost_source_rating']);
            }
        }

        // Базовая система рейтинга: фиксированные значения
        // Можно улучшить, учитывая разницу рейтингов
        $ratingChange = $this->calculateRatingChange($initiatorRating, $opponentRating);
        if ($isGhostMatch) {
            $ratingChange = max(1, (int) round($ratingChange * self::GHOST_RATING_COEFFICIENT));
        }

        $initiatorRatingChange = 0;
        $opponentRatingChange = 0;

        switch ($resultStatus) {
            case 'initiator_win':
                $initiatorProfile->duel_wins++;
                $initiatorRatingChange = $ratingChange;
                $initiatorProfile->rating = max(0, $initiatorRating + $ratingChange);
                $initiatorProfile->streak_days = (int) $initiatorProfile->streak_days + 1;

                if (!$isGhostMatch) {
                    $opponentProfile->duel_losses++;
                    $opponentRatingChange = -$ratingChange;
                    $opponentProfile->rating = max(0, $opponentRating - $ratingChange);
                    $opponentProfile->streak_days = 0;
                }
                break;
            case 'opponent_win':
                if (!$isGhostMatch) {
                    $opponentProfile->duel_wins++;
                    $opponentRatingChange = $ratingChange;
                    $opponentProfile->rating = max(0, $opponentRating + $ratingChange);
                    $opponentProfile->streak_days = (int) $opponentProfile->streak_days + 1;
                }

                $initiatorProfile->duel_losses++;
                $initiatorRatingChange = -$ratingChange;
                $initiatorProfile->rating = max(0, $initiatorRating - $ratingChange);
                $initiatorProfile->streak_days = 0;
                break;
            default:
                // Ничья: рейтинг не меняется
                $initiatorProfile->duel_draws++;
                if (!$isGhostMatch) {
                    $opponentProfile->duel_draws++;
                }
                break;
        }

        $initiatorProfile->save();
        if (!$isGhostMatch) {
            $opponentProfile->save();
        }

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
