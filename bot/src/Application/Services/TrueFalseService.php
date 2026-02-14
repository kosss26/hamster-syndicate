<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\TrueFalseFact;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TrueFalseService
{
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
            'current_fact_id' => $fact->getKey(),
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
    public function handleAnswer(User $user, int $factId, bool $answerIsTrue): array
    {
        $user = $this->userService->ensureProfile($user);
        $session = $this->getSession($user) ?? [
            'streak' => 0,
            'asked' => [],
            'current_fact_id' => null,
        ];

        /** @var TrueFalseFact|null $fact */
        $fact = TrueFalseFact::query()
            ->where('is_active', true)
            ->find($factId);

        if (!$fact instanceof TrueFalseFact) {
            $this->logger->warning('Факт не найден для режима Правда или ложь', [
                'fact_id' => $factId,
            ]);

            return [
                'fact' => null,
                'is_correct' => false,
                'explanation' => null,
                'correct_answer' => false,
                'streak' => (int) ($session['streak'] ?? 0),
                'record' => (int) ($user->profile?->true_false_record ?? 0),
                'record_updated' => false,
                'next_fact' => null,
            ];
        }

        $isCorrect = $fact->is_true === $answerIsTrue;
        $streak = $isCorrect
            ? (int) ($session['streak'] ?? 0) + 1
            : 0;

        $session['streak'] = $streak;
        $session['current_fact_id'] = null;

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
        $nextFact = $this->getRandomFact($asked, $preferredIsTrue);

        if (!$nextFact instanceof TrueFalseFact) {
            $nextFact = $this->getRandomFact($asked);
        }

        if (!$nextFact instanceof TrueFalseFact) {
            // Если факты закончились, начинаем заново
            $session['asked'] = [];
            $session['asked_true'] = 0;
            $session['asked_false'] = 0;
            $preferredIsTrue = random_int(0, 1) === 1;
            $nextFact = $this->getRandomFact([], $preferredIsTrue);

            if (!$nextFact instanceof TrueFalseFact) {
                $nextFact = $this->getRandomFact([]);
            }
        }

        if ($nextFact instanceof TrueFalseFact) {
            $session['current_fact_id'] = $nextFact->getKey();
            $session['asked'][] = $nextFact->getKey();
            if ($nextFact->is_true) {
                $session['asked_true'] = (int) ($session['asked_true'] ?? 0) + 1;
            } else {
                $session['asked_false'] = (int) ($session['asked_false'] ?? 0) + 1;
            }
        }

        $this->saveSession($user, $session);

        $recordUpdated = false;
        $record = (int) ($user->profile?->true_false_record ?? 0);

        if ($isCorrect && $streak > $record) {
            $this->updateRecord($user, $streak);
            $record = $streak;
            $recordUpdated = true;
        }

        return [
            'fact' => $fact,
            'is_correct' => $isCorrect,
            'explanation' => $fact->explanation,
            'correct_answer' => (bool) $fact->is_true,
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
            'current_fact_id' => null,
            'asked_true' => 0,
            'asked_false' => 0,
        ];

        $session['streak'] = 0;

        $asked = $session['asked'] ?? [];
        $preferredIsTrue = $this->resolvePreferredTruthiness($session);
        $fact = $this->getRandomFact($asked, $preferredIsTrue);

        if (!$fact instanceof TrueFalseFact) {
            $fact = $this->getRandomFact($asked);
        }

        if (!$fact instanceof TrueFalseFact) {
            $session['asked'] = [];
            $session['asked_true'] = 0;
            $session['asked_false'] = 0;
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
            $session['current_fact_id'] = $fact->getKey();
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

    private function updateRecord(User $user, int $streak): void
    {
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            return;
        }

        $profile->true_false_record = $streak;
        $profile->save();
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
