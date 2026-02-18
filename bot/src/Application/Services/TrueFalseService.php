<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\TrueFalseFact;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TrueFalseService
{
    private const RECENT_FACTS_WINDOW = 20;
    private const QUESTION_TIME_LIMIT_SECONDS = 15;

    private CacheInterface $cache;

    private Logger $logger;

    private UserService $userService;

    public function __construct(
        CacheInterface $cache,
        Logger $logger,
        UserService $userService
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->userService = $userService;
    }

    public function startSession(User $user): ?TrueFalseFact
    {
        $user = $this->userService->ensureProfile($user);
        $desiredTruthiness = random_int(0, 1) === 1;
        $fact = $this->getRandomFact([], $desiredTruthiness);

        if (!$fact instanceof TrueFalseFact) {
            $fact = $this->getRandomFact([]);
        }

        if (!$fact instanceof TrueFalseFact) {
            $this->logger->warning('Нет фактов для режима Правда или ложь');

            return null;
        }

        $session = [
            'streak' => 0,
            'asked' => [$fact->getKey()],
            'recent_ids' => [$fact->getKey()],
            'current_fact_id' => $fact->getKey(),
            'current_fact_started_at' => Carbon::now()->toIso8601String(),
            'asked_true' => $fact->is_true ? 1 : 0,
            'asked_false' => $fact->is_true ? 0 : 1,
        ];

        $this->saveSession($user, $session);

        return $fact;
    }

    /**
     * @return array{
     *     fact: ?TrueFalseFact,
     *     is_correct: bool,
     *     explanation: string|null,
     *     correct_answer: bool,
     *     streak: int,
     *     record: int,
     *     record_updated: bool,
     *     next_fact: ?TrueFalseFact
     * }
     */
    public function handleAnswer(User $user, int $factId, bool $answerIsTrue, bool $forceTimeout = false): array
    {
        $user = $this->userService->ensureProfile($user);
        $session = $this->getSession($user) ?? [
            'streak' => 0,
            'asked' => [],
            'recent_ids' => [],
            'current_fact_id' => null,
            'current_fact_started_at' => null,
        ];

        /** @var TrueFalseFact|null $fact */
        $fact = TrueFalseFact::query()
            ->where('is_active', true)
            ->find($factId);

        if (!$fact instanceof TrueFalseFact) {
            $profile = $user->profile;
            $recordValue = $profile instanceof UserProfile ? (int) $profile->true_false_record : 0;
            $this->logger->warning('Факт не найден для режима Правда или ложь', [
                'fact_id' => $factId,
            ]);

            return [
                'fact' => null,
                'is_correct' => false,
                'explanation' => null,
                'correct_answer' => false,
                'timed_out' => false,
                'streak' => (int) ($session['streak'] ?? 0),
                'record' => $recordValue,
                'record_updated' => false,
                'next_fact' => null,
            ];
        }

        $timedOutByClock = $this->isAnswerTimedOut($session, $factId, self::QUESTION_TIME_LIMIT_SECONDS);
        $timedOut = $forceTimeout || $timedOutByClock;
        $effectiveAnswerIsTrue = $timedOut ? !(bool) $fact->is_true : $answerIsTrue;
        $isCorrect = $fact->is_true === $effectiveAnswerIsTrue;
        $streak = $isCorrect
            ? (int) ($session['streak'] ?? 0) + 1
            : 0;

        $session['streak'] = $streak;
        $session['current_fact_id'] = null;
        $session['current_fact_started_at'] = null;
        $this->pushRecentFactId($session, (int) $fact->getKey());

        $asked = $session['asked'] ?? [];
        $factWasAddedToAsked = false;
        if (!\in_array($fact->getKey(), $asked, true)) {
            $asked[] = $fact->getKey();
            $factWasAddedToAsked = true;
        }
        $session['asked'] = $asked;

        if ($factWasAddedToAsked) {
            if ($fact->is_true) {
                $session['asked_true'] = (int) ($session['asked_true'] ?? 0) + 1;
            } else {
                $session['asked_false'] = (int) ($session['asked_false'] ?? 0) + 1;
            }
        }

        $preferredIsTrue = $this->resolvePreferredTruthiness($session);
        $recentExcludeIds = $this->getRecentExcludeIds($session);
        $nextFact = $this->getRandomFact($recentExcludeIds, $preferredIsTrue);

        if (!$nextFact instanceof TrueFalseFact) {
            $nextFact = $this->getRandomFact($recentExcludeIds);
        }

        if (!$nextFact instanceof TrueFalseFact) {
            // Если окно уникальности слишком большое для текущего пула,
            // мягко сбрасываем окно и продолжаем с балансом.
            $session['recent_ids'] = [];
            $preferredIsTrue = random_int(0, 1) === 1;
            $nextFact = $this->getRandomFact([], $preferredIsTrue);

            if (!$nextFact instanceof TrueFalseFact) {
                $nextFact = $this->getRandomFact([]);
            }
        }

        if ($nextFact instanceof TrueFalseFact) {
            $session['current_fact_id'] = $nextFact->getKey();
            $session['current_fact_started_at'] = Carbon::now()->toIso8601String();
            $session['asked'][] = $nextFact->getKey();
            $this->pushRecentFactId($session, (int) $nextFact->getKey());
            if ($nextFact->is_true) {
                $session['asked_true'] = (int) ($session['asked_true'] ?? 0) + 1;
            } else {
                $session['asked_false'] = (int) ($session['asked_false'] ?? 0) + 1;
            }
        }

        $this->saveSession($user, $session);

        $recordUpdated = false;
        $profile = $user->profile;
        $record = $profile instanceof UserProfile ? (int) $profile->true_false_record : 0;

        if ($isCorrect && $streak > $record) {
            $this->updateRecord($user, $streak);
            $record = $streak;
            $recordUpdated = true;
        }

        return [
            'fact' => $fact,
            'is_correct' => $isCorrect,
            'explanation' => $timedOut ? 'Время вышло!' : $fact->explanation,
            'correct_answer' => (bool) $fact->is_true,
            'timed_out' => $timedOut,
            'streak' => $streak,
            'record' => $record,
            'record_updated' => $recordUpdated,
            'next_fact' => $nextFact,
        ];
    }

    public function skip(User $user): ?TrueFalseFact
    {
        $user = $this->userService->ensureProfile($user);
        $session = $this->getSession($user) ?? [
            'streak' => 0,
            'asked' => [],
            'recent_ids' => [],
            'current_fact_id' => null,
            'current_fact_started_at' => null,
            'asked_true' => 0,
            'asked_false' => 0,
        ];

        $session['streak'] = 0;

        $preferredIsTrue = $this->resolvePreferredTruthiness($session);
        $recentExcludeIds = $this->getRecentExcludeIds($session);
        $fact = $this->getRandomFact($recentExcludeIds, $preferredIsTrue);

        if (!$fact instanceof TrueFalseFact) {
            $fact = $this->getRandomFact($recentExcludeIds);
        }

        if (!$fact instanceof TrueFalseFact) {
            $session['recent_ids'] = [];
            $fact = $this->getRandomFact([], random_int(0, 1) === 1);
            if (!$fact instanceof TrueFalseFact) {
                $fact = $this->getRandomFact([]);
            }
        }

        if ($fact instanceof TrueFalseFact) {
            $factWasAddedToAsked = false;
            if (!\in_array($fact->getKey(), $session['asked'], true)) {
                $session['asked'][] = $fact->getKey();
                $factWasAddedToAsked = true;
            }
            $this->pushRecentFactId($session, (int) $fact->getKey());
            $session['current_fact_id'] = $fact->getKey();
            $session['current_fact_started_at'] = Carbon::now()->toIso8601String();
            if ($factWasAddedToAsked) {
                if ($fact->is_true) {
                    $session['asked_true'] = (int) ($session['asked_true'] ?? 0) + 1;
                } else {
                    $session['asked_false'] = (int) ($session['asked_false'] ?? 0) + 1;
                }
            }
            $this->saveSession($user, $session);
        }

        return $fact;
    }

    public function getCurrentFact(User $user): ?TrueFalseFact
    {
        $session = $this->getSession($user);
        $currentId = $session['current_fact_id'] ?? null;

        if ($currentId === null) {
            return null;
        }

        return TrueFalseFact::query()
            ->where('is_active', true)
            ->find($currentId);
    }

    /**
     * @return array{time_limit:int,started_at:?string,expires_at:?string,time_left:int,is_expired:bool}
     */
    public function getCurrentTiming(User $user): array
    {
        $session = $this->getSession($user) ?? [];
        $timeLimit = self::QUESTION_TIME_LIMIT_SECONDS;
        $startedAtRaw = $session['current_fact_started_at'] ?? null;
        $startedAt = null;
        if (is_string($startedAtRaw) && trim($startedAtRaw) !== '') {
            try {
                $startedAt = Carbon::parse($startedAtRaw);
            } catch (\Throwable) {
                $startedAt = null;
            }
        }

        if (!$startedAt instanceof Carbon) {
            return [
                'time_limit' => $timeLimit,
                'started_at' => null,
                'expires_at' => null,
                'time_left' => $timeLimit,
                'is_expired' => false,
            ];
        }

        $expiresAt = $startedAt->copy()->addSeconds($timeLimit);
        $timeLeft = max(0, $expiresAt->getTimestamp() - Carbon::now()->getTimestamp());

        return [
            'time_limit' => $timeLimit,
            'started_at' => $startedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'time_left' => $timeLeft,
            'is_expired' => $timeLeft <= 0,
        ];
    }

    public function getQuestionTimeLimit(): int
    {
        return self::QUESTION_TIME_LIMIT_SECONDS;
    }

    /**
     * @param array<int> $excludeIds
     */
    private function getRandomFact(array $excludeIds, ?bool $isTrue = null): ?TrueFalseFact
    {
        $query = TrueFalseFact::query()
            ->where('is_active', true);

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        if ($isTrue !== null) {
            $query->where('is_true', $isTrue);
        }

        return $query->inRandomOrder()->first();
    }

    /**
     * @param array<string, mixed> $session
     */
    private function resolvePreferredTruthiness(array $session): bool
    {
        $askedTrue = (int) ($session['asked_true'] ?? 0);
        $askedFalse = (int) ($session['asked_false'] ?? 0);

        if ($askedTrue === $askedFalse) {
            return random_int(0, 1) === 1;
        }

        return $askedTrue < $askedFalse;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<int, int>
     */
    private function getRecentExcludeIds(array $session): array
    {
        $recent = $session['recent_ids'] ?? [];
        if (!is_array($recent)) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $recent), static fn (int $id): bool => $id > 0));
        if (count($ids) <= self::RECENT_FACTS_WINDOW) {
            return $ids;
        }

        return array_slice($ids, -self::RECENT_FACTS_WINDOW);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function pushRecentFactId(array &$session, int $factId): void
    {
        if ($factId <= 0) {
            return;
        }

        $recent = $session['recent_ids'] ?? [];
        if (!is_array($recent)) {
            $recent = [];
        }

        $recent[] = $factId;
        if (count($recent) > self::RECENT_FACTS_WINDOW) {
            $recent = array_slice($recent, -self::RECENT_FACTS_WINDOW);
        }

        $session['recent_ids'] = array_values($recent);
    }

    private function updateRecord(User $user, int $streak): void
    {
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            return;
        }

        $profile->true_false_record = $streak;
        $profile->save();
    }

    /**
     * @param array<string, mixed> $session
     */
    private function isAnswerTimedOut(array $session, int $factId, int $timeLimit): bool
    {
        if ($factId <= 0) {
            return true;
        }

        $currentFactId = (int) ($session['current_fact_id'] ?? 0);
        if ($currentFactId > 0 && $currentFactId !== $factId) {
            return true;
        }

        $startedAtRaw = $session['current_fact_started_at'] ?? null;
        if (!is_string($startedAtRaw) || trim($startedAtRaw) === '') {
            return false;
        }

        try {
            $startedAt = Carbon::parse($startedAtRaw);
        } catch (\Throwable) {
            return false;
        }

        $expiresAt = $startedAt->copy()->addSeconds(max(1, $timeLimit));
        return $expiresAt->lte(Carbon::now());
    }

    private function getSessionKey(User $user): string
    {
        return sprintf('true_false_session:%d', $user->getKey());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSession(User $user): ?array
    {
        try {
            return $this->cache->get($this->getSessionKey($user), static function (): ?array {
                return null;
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось получить сессию режима Правда или ложь', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $session
     */
    private function saveSession(User $user, array $session): void
    {
        try {
            $this->cache->delete($this->getSessionKey($user));
            $this->cache->get($this->getSessionKey($user), function (ItemInterface $item) use ($session): array {
                $item->expiresAfter(3600);

                return $session;
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось сохранить сессию режима Правда или ложь', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);
        }
    }
}
