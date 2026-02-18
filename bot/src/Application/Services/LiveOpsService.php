<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
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

        $weekly = $this->buildWeeklyChallenge($user, $now);
        $missions = $this->buildMissions($user, $now);
        $season = $this->buildSeasonProgress($user, $now);

        return [
            'generated_at' => $now->toIso8601String(),
            'weekly_challenge' => $weekly,
            'missions' => $missions,
            'season' => $season,
            'summary' => [
                'available_claims' => (int) ($weekly['can_claim'] ? 1 : 0) + count(array_filter($missions, static fn (array $item): bool => (bool) ($item['can_claim'] ?? false))),
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

        $missions = $dashboard['missions'] ?? [];
        if (is_array($missions)) {
            foreach ($missions as $mission) {
                if (is_array($mission) && (string) ($mission['claim_key'] ?? '') === $claimKey) {
                    return $mission;
                }
            }
        }

        return null;
    }

    private function buildWeeklyChallenge(User $user, Carbon $now): array
    {
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addWeek();
        $target = 5;

        $wins = DuelResult::query()
            ->where('winner_user_id', $user->getKey())
            ->where('created_at', '>=', $weekStart)
            ->where('created_at', '<', $weekEnd)
            ->count();

        $claimKey = sprintf('weekly_duel_wins_%s', $weekStart->format('oW'));
        $claimed = $this->isClaimed((int) $user->getKey(), $claimKey);

        return [
            'id' => 'weekly_duel_wins',
            'title' => 'Недельный челлендж: победы в дуэлях',
            'description' => 'Выиграй 5 дуэлей за неделю',
            'period' => [
                'start' => $weekStart->toIso8601String(),
                'end' => $weekEnd->toIso8601String(),
                'key' => $weekStart->format('o-W'),
            ],
            'target' => $target,
            'progress' => min($target, (int) $wins),
            'claimed' => $claimed,
            'claim_key' => $claimKey,
            'can_claim' => !$claimed && $wins >= $target,
            'reward' => [
                'coins' => 220,
                'experience' => 120,
                'tickets' => 1,
            ],
        ];
    }

    private function buildMissions(User $user, Carbon $now): array
    {
        $accountAgeDays = max(0, (int) $user->created_at?->startOfDay()->diffInDays($now->copy()->startOfDay()));

        $totalFinishedDuels = Duel::query()
            ->where('status', 'finished')
            ->where(function ($q) use ($user): void {
                $q->where('initiator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->count();

        $duelWinsTotal = (int) ($user->profile?->duel_wins ?? 0);

        $friendInvitesStarted = Duel::query()
            ->where('initiator_user_id', $user->getKey())
            ->where('settings', 'like', '%"awaiting_target":true%')
            ->count();

        $definitions = [
            [
                'id' => 'mission_day1_play_duel',
                'title' => 'Миссия 1-го дня',
                'description' => 'Сыграй 1 завершённую дуэль',
                'unlock_after_days' => 0,
                'target' => 1,
                'value' => $totalFinishedDuels,
                'reward' => ['coins' => 120, 'experience' => 60, 'tickets' => 0],
            ],
            [
                'id' => 'mission_day3_win_duels',
                'title' => 'Миссия 3-го дня',
                'description' => 'Выиграй 3 дуэли',
                'unlock_after_days' => 2,
                'target' => 3,
                'value' => $duelWinsTotal,
                'reward' => ['coins' => 180, 'experience' => 90, 'tickets' => 1],
            ],
            [
                'id' => 'mission_day7_invite_friend',
                'title' => 'Миссия 7-го дня',
                'description' => 'Создай 1 приватную дуэль с другом',
                'unlock_after_days' => 6,
                'target' => 1,
                'value' => $friendInvitesStarted,
                'reward' => ['coins' => 260, 'experience' => 140, 'tickets' => 1],
            ],
        ];

        $missions = [];

        foreach ($definitions as $def) {
            $claimKey = (string) ($def['id'] ?? '');
            $target = (int) ($def['target'] ?? 1);
            $value = (int) ($def['value'] ?? 0);
            $locked = $accountAgeDays < (int) ($def['unlock_after_days'] ?? 0);
            $claimed = $this->isClaimed((int) $user->getKey(), $claimKey);

            $missions[] = [
                'id' => $claimKey,
                'title' => (string) ($def['title'] ?? $claimKey),
                'description' => (string) ($def['description'] ?? ''),
                'unlock_after_days' => (int) ($def['unlock_after_days'] ?? 0),
                'account_age_days' => $accountAgeDays,
                'locked' => $locked,
                'target' => $target,
                'progress' => min($target, $value),
                'claimed' => $claimed,
                'claim_key' => $claimKey,
                'can_claim' => !$locked && !$claimed && $value >= $target,
                'reward' => $def['reward'],
            ];
        }

        return $missions;
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
