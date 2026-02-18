<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelResult;
use QuizBot\Domain\Model\ShopPurchase;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserAnswerHistory;
use QuizBot\Domain\Model\UserLiveOpsClaim;

class LiveOpsService
{
    private Logger $logger;
    private UserService $userService;
    private TicketService $ticketService;

    public function __construct(Logger $logger, UserService $userService, TicketService $ticketService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->ticketService = $ticketService;
    }

    public function getDashboard(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;
        $now = Carbon::now();

        $dailyMissions = $this->buildDailyMissions($user, $now);
        $weeklyMissions = $this->buildWeeklyMissions($user, $now);
        $missions = array_merge($dailyMissions, $weeklyMissions);
        $season = $this->buildSeasonProgress($user, $now);
        $availableClaims = count(array_filter($missions, static fn (array $item): bool => (bool) ($item['can_claim'] ?? false)));
        $weeklyHighlight = $weeklyMissions[0] ?? null;

        return [
            'generated_at' => $now->toIso8601String(),
            'weekly_challenge' => $weeklyHighlight,
            'daily_missions' => $dailyMissions,
            'weekly_missions' => $weeklyMissions,
            'missions' => $missions,
            'season' => $season,
            'summary' => [
                'available_claims' => $availableClaims,
                'daily_missions_count' => count($dailyMissions),
                'weekly_missions_count' => count($weeklyMissions),
                'duel_wins_total' => (int) ($profile->duel_wins ?? 0),
                'tickets' => (int) ($profile->lives ?? 0),
            ],
        ];
    }

    public function claim(User $user, string $claimKey): array
    {
        $normalizedKey = trim($claimKey);
        if ($normalizedKey === '') {
            throw new \InvalidArgumentException('Пустой claim_key');
        }

        $user = $this->userService->ensureProfile($user);
        $dashboard = $this->getDashboard($user);

        $claimTarget = $this->findClaimTarget($dashboard, $normalizedKey);
        if ($claimTarget === null) {
            throw new \RuntimeException('Награда не найдена');
        }

        if (!empty($claimTarget['claimed'])) {
            throw new \RuntimeException('Награда уже получена');
        }

        if (empty($claimTarget['can_claim'])) {
            throw new \RuntimeException('Награда пока недоступна');
        }

        $reward = is_array($claimTarget['reward'] ?? null) ? $claimTarget['reward'] : [];

        $xpResult = \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($user, $normalizedKey, $reward): array {
            if ($this->isClaimed((int) $user->getKey(), $normalizedKey)) {
                throw new \RuntimeException('Награда уже получена');
            }

            UserLiveOpsClaim::query()->create([
                'user_id' => (int) $user->getKey(),
                'claim_key' => $normalizedKey,
                'payload' => [
                    'reward' => $reward,
                ],
            ]);

            return $this->applyReward($user, $reward);
        });

        $dashboardAfter = $this->getDashboard($user->refresh());

        return [
            'claim_key' => $normalizedKey,
            'reward' => $reward,
            'experience' => $xpResult,
            'dashboard' => $dashboardAfter,
        ];
    }

    private function findClaimTarget(array $dashboard, string $claimKey): ?array
    {
        $weekly = $dashboard['weekly_challenge'] ?? null;
        if (is_array($weekly) && (string) ($weekly['claim_key'] ?? '') === $claimKey) {
            return $weekly;
        }

        foreach (['daily_missions', 'weekly_missions', 'missions'] as $collectionKey) {
            $items = $dashboard[$collectionKey] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (is_array($item) && (string) ($item['claim_key'] ?? '') === $claimKey) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function buildDailyMissions(User $user, Carbon $now): array
    {
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $dailyCategory = $this->resolveDailyChallengeCategory($dayStart);
        $dailyCategoryId = $dailyCategory ? (int) $dailyCategory->getKey() : null;
        $dailyCategoryTitle = $dailyCategory ? (string) $dailyCategory->title : 'Общие знания';

        $definitions = [
            [
                'id' => 'daily_finish_duels',
                'title' => 'Ежедневка: сыграй дуэли',
                'description' => 'Заверши 3 дуэли за день',
                'target' => 3,
                'value' => $this->countFinishedDuels($user, $dayStart, $dayEnd),
                'reward' => ['coins' => 120, 'experience' => 60, 'tickets' => 0],
            ],
            [
                'id' => 'daily_truefalse_correct',
                'title' => 'Ежедневка: правда или ложь',
                'description' => 'Ответь правильно на 10 фактов в режиме П/Л',
                'target' => 10,
                'value' => $this->countCorrectAnswers($user, $dayStart, $dayEnd, 'truefalse'),
                'reward' => ['coins' => 140, 'experience' => 70, 'tickets' => 1],
            ],
            [
                'id' => 'daily_category_correct_10',
                'title' => 'Ежедневка: категория дня',
                'description' => sprintf('Ответь правильно на 10 вопросов из категории %s', $dailyCategoryTitle),
                'target' => 10,
                'value' => $dailyCategoryId ? $this->countCorrectAnswers($user, $dayStart, $dayEnd, null, $dailyCategoryId) : 0,
                'reward' => ['coins' => 180, 'experience' => 100, 'tickets' => 1],
                'meta' => [
                    'category_id' => $dailyCategoryId,
                    'category_title' => $dailyCategoryTitle,
                ],
            ],
        ];

        return $this->buildMissionCollection(
            $user,
            $definitions,
            $dayStart,
            $dayEnd,
            'daily',
            $dayStart->format('Ymd')
        );
    }

    private function buildWeeklyMissions(User $user, Carbon $now): array
    {
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addWeek();

        $definitions = [
            [
                'id' => 'weekly_duel_wins',
                'title' => 'Недельный челлендж: победы',
                'description' => 'Выиграй 8 дуэлей за неделю',
                'target' => 8,
                'value' => $this->countDuelWins($user, $weekStart, $weekEnd),
                'reward' => ['coins' => 320, 'experience' => 180, 'tickets' => 1],
            ],
            [
                'id' => 'weekly_friend_invites',
                'title' => 'Недельная миссия: с друзьями',
                'description' => 'Создай 3 приватные дуэли за неделю',
                'target' => 3,
                'value' => $this->countFriendInvites($user, $weekStart, $weekEnd),
                'reward' => ['coins' => 260, 'experience' => 140, 'tickets' => 1],
            ],
            [
                'id' => 'weekly_correct_answers',
                'title' => 'Недельная миссия: точность',
                'description' => 'Дай 60 правильных ответов в любых режимах',
                'target' => 60,
                'value' => $this->countCorrectAnswers($user, $weekStart, $weekEnd),
                'reward' => ['coins' => 360, 'experience' => 210, 'tickets' => 2],
            ],
        ];

        return $this->buildMissionCollection(
            $user,
            $definitions,
            $weekStart,
            $weekEnd,
            'weekly',
            $weekStart->format('oW')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function buildMissionCollection(User $user, array $definitions, Carbon $periodStart, Carbon $periodEnd, string $frequency, string $periodKey): array
    {
        $missions = [];

        foreach ($definitions as $def) {
            $id = (string) ($def['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $claimKey = sprintf('%s_%s', $id, $periodKey);
            $target = (int) ($def['target'] ?? 1);
            $value = (int) ($def['value'] ?? 0);
            $claimed = $this->isClaimed((int) $user->getKey(), $claimKey);

            $missions[] = [
                'id' => $id,
                'frequency' => $frequency,
                'title' => (string) ($def['title'] ?? $id),
                'description' => (string) ($def['description'] ?? ''),
                'period' => [
                    'start' => $periodStart->toIso8601String(),
                    'end' => $periodEnd->toIso8601String(),
                    'key' => $periodKey,
                ],
                'locked' => false,
                'target' => $target,
                'progress' => min($target, $value),
                'claimed' => $claimed,
                'claim_key' => $claimKey,
                'can_claim' => !$claimed && $value >= $target,
                'reward' => is_array($def['reward'] ?? null) ? $def['reward'] : [],
                'meta' => is_array($def['meta'] ?? null) ? $def['meta'] : null,
            ];
        }

        return $missions;
    }

    private function resolveDailyChallengeCategory(Carbon $dayStart): ?Category
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('questions', function ($q): void {
                $q->where('is_active', true);
            })
            ->orderBy('id')
            ->get();

        $count = $categories->count();
        if ($count === 0) {
            return null;
        }

        $dayIndex = max(0, ((int) $dayStart->dayOfYear) - 1);
        $slot = $dayIndex % $count;

        return $categories->values()->get($slot);
    }

    private function countFinishedDuels(User $user, Carbon $start, Carbon $end): int
    {
        return Duel::query()
            ->where('status', 'finished')
            ->where(function ($q) use ($start, $end): void {
                $q->where(function ($inner) use ($start, $end): void {
                    $inner->whereNotNull('finished_at')
                        ->where('finished_at', '>=', $start)
                        ->where('finished_at', '<', $end);
                })->orWhere(function ($inner) use ($start, $end): void {
                    $inner->whereNull('finished_at')
                        ->where('created_at', '>=', $start)
                        ->where('created_at', '<', $end);
                });
            })
            ->where(function ($q) use ($user): void {
                $q->where('initiator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->count();
    }

    private function countDuelWins(User $user, Carbon $start, Carbon $end): int
    {
        return DuelResult::query()
            ->where('winner_user_id', $user->getKey())
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countFriendInvites(User $user, Carbon $start, Carbon $end): int
    {
        return Duel::query()
            ->where('initiator_user_id', $user->getKey())
            ->where('settings', 'like', '%"awaiting_target":true%')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countCorrectAnswers(User $user, Carbon $start, Carbon $end, ?string $mode = null, ?int $categoryId = null): int
    {
        $query = UserAnswerHistory::query()
            ->where('user_id', $user->getKey())
            ->where('is_correct', true)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);

        if ($mode !== null && $mode !== '') {
            $query->where('mode', $mode);
        }

        if ($categoryId !== null && $categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        return $query->count();
    }

    private function buildSeasonProgress(User $user, Carbon $now): array
    {
        $seasonStart = $now->copy()->startOfMonth()->startOfDay();
        $seasonEnd = $seasonStart->copy()->addMonth();
        $seasonKey = $seasonStart->format('Y-m');

        $duelWins = DuelResult::query()
            ->where('winner_user_id', $user->getKey())
            ->where('created_at', '>=', $seasonStart)
            ->where('created_at', '<', $seasonEnd)
            ->count();

        $trueFalseCorrect = UserAnswerHistory::query()
            ->where('user_id', $user->getKey())
            ->where('mode', 'truefalse')
            ->where('is_correct', true)
            ->where('created_at', '>=', $seasonStart)
            ->where('created_at', '<', $seasonEnd)
            ->count();

        $purchases = ShopPurchase::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', $seasonStart)
            ->where('created_at', '<', $seasonEnd)
            ->count();

        $friendInvites = Duel::query()
            ->where('initiator_user_id', $user->getKey())
            ->where('settings', 'like', '%"awaiting_target":true%')
            ->where('created_at', '>=', $seasonStart)
            ->where('created_at', '<', $seasonEnd)
            ->count();

        $points = ($duelWins * 10) + ($trueFalseCorrect * 2) + ($purchases * 5) + ($friendInvites * 8);
        $pointsPerLevel = 100;
        $maxLevel = 50;
        $level = min($maxLevel, 1 + intdiv($points, $pointsPerLevel));
        $pointsIntoLevel = $points % $pointsPerLevel;

        return [
            'id' => 'season_monthly_progress',
            'title' => 'Сезонный прогресс',
            'description' => 'Побеждай, отвечай и играй с друзьями, чтобы качать сезон',
            'season_key' => $seasonKey,
            'period' => [
                'start' => $seasonStart->toIso8601String(),
                'end' => $seasonEnd->toIso8601String(),
            ],
            'level' => $level,
            'max_level' => $maxLevel,
            'points_total' => $points,
            'points_per_level' => $pointsPerLevel,
            'points_into_level' => $pointsIntoLevel,
            'points_to_next_level' => max(0, $pointsPerLevel - $pointsIntoLevel),
            'sources' => [
                'duel_wins' => (int) $duelWins,
                'truefalse_correct' => (int) $trueFalseCorrect,
                'shop_purchases' => (int) $purchases,
                'friend_invites' => (int) $friendInvites,
            ],
        ];
    }

    private function applyReward(User $user, array $reward): array
    {
        $coins = max(0, (int) ($reward['coins'] ?? 0));
        $experience = max(0, (int) ($reward['experience'] ?? 0));
        $tickets = max(0, (int) ($reward['tickets'] ?? 0));

        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if ($coins > 0) {
            $profile->coins = (int) ($profile->coins ?? 0) + $coins;
        }

        if ($tickets > 0) {
            $profile->lives = min(TicketService::TICKET_CAP, (int) ($profile->lives ?? 0) + $tickets);
        }

        $profile->save();

        $xpResult = $experience > 0
            ? $this->userService->grantExperience($user, $experience)
            : [
                'added' => 0,
                'old_level' => (int) ($profile->level ?? 1),
                'new_level' => (int) ($profile->level ?? 1),
                'leveled_up' => false,
                'total_experience' => (int) ($profile->experience ?? 0),
                'progress' => $this->userService->getExperienceProgress($profile),
            ];

        return $xpResult;
    }

    private function isClaimed(int $userId, string $claimKey): bool
    {
        if ($userId <= 0 || $claimKey === '') {
            return false;
        }

        return UserLiveOpsClaim::query()
            ->where('user_id', $userId)
            ->where('claim_key', $claimKey)
            ->exists();
    }
}
