<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\Referral;
use QuizBot\Domain\Model\ReferralMilestone;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ReferralService
{
    private const BASE_REFERRER_REWARD_COINS = 100;
    private const BASE_REFERRER_REWARD_EXP = 50;
    private const BASE_REFERRED_REWARD_COINS = 50;
    private const BASE_REFERRED_REWARD_EXP = 25;
    
    private const ACTIVATION_REQUIRED_GAMES = 3;

    private Logger $logger;
    private UserService $userService;

    public function __construct(Logger $logger, UserService $userService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
    }

    /**
     * Генерирует уникальный реферальный код для пользователя
     */
    public function generateReferralCode(User $user): string
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if ($profile->referral_code) {
            return $profile->referral_code;
        }

        // Генерируем код: первые буквы имени + случайные символы
        $prefix = mb_substr($user->first_name ?? 'USER', 0, 3);
        $prefix = Str::upper(Str::ascii($prefix));
        
        $attempts = 0;
        do {
            $random = Str::upper(Str::random(6));
            $code = $prefix . $random;
            
            $exists = UserProfile::where('referral_code', $code)->exists();
            $attempts++;
            
            if ($attempts > 10) {
                // Fallback: полностью случайный код
                $code = Str::upper(Str::random(9));
            }
        } while ($exists && $attempts < 20);

        $profile->referral_code = $code;
        $profile->save();

        $this->logger->info('Сгенерирован реферальный код', [
            'user_id' => $user->getKey(),
            'code' => $code,
        ]);

        return $code;
    }

    /**
     * Применяет реферальный код для нового пользователя
     */
    public function applyReferralCode(User $referred, string $code): array
    {
        $referred = $this->userService->ensureProfile($referred);
        
        // Проверка: уже использовал реферальный код
        if ($referred->profile->referred_by_user_id !== null) {
            return [
                'success' => false,
                'error' => 'Вы уже использовали реферальный код',
            ];
        }

        // Поиск пользователя с таким кодом
        $referrerProfile = UserProfile::where('referral_code', $code)->first();
        
        if (!$referrerProfile) {
            return [
                'success' => false,
                'error' => 'Реферальный код не найден',
            ];
        }

        $referrer = $referrerProfile->user;

        // Проверка: нельзя пригласить самого себя
        if ($referrer->getKey() === $referred->getKey()) {
            return [
                'success' => false,
                'error' => 'Нельзя использовать свой собственный код',
            ];
        }

        // Создаем запись реферала
        $referral = new Referral([
            'referrer_user_id' => $referrer->getKey(),
            'referred_user_id' => $referred->getKey(),
            'referral_code' => $code,
            'status' => 'pending',
            'referred_completed_onboarding' => true,
        ]);
        $referral->save();

        // Обновляем профиль приглашенного
        $referred->profile->referred_by_user_id = $referrer->getKey();
        $referred->profile->save();

        // Даем начальную награду приглашенному сразу
        $this->giveImmediateReward($referred, $referral);

        $this->logger->info('Применен реферальный код', [
            'referrer_id' => $referrer->getKey(),
            'referred_id' => $referred->getKey(),
            'code' => $code,
        ]);

        return [
            'success' => true,
            'referrer' => $referrer,
            'reward_coins' => self::BASE_REFERRED_REWARD_COINS,
            'reward_experience' => self::BASE_REFERRED_REWARD_EXP,
        ];
    }

    /**
     * Проверяет и активирует реферала после выполнения условий
     */
    public function checkAndActivateReferral(User $referred): bool
    {
        $referral = Referral::where('referred_user_id', $referred->getKey())
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return false;
        }

        // Подсчитываем сыгранные игры
        $gamesPlayed = $this->countUserGames($referred);
        $referral->referred_games_played = $gamesPlayed;
        $referral->save();

        // Проверяем условие активации
        if ($gamesPlayed >= self::ACTIVATION_REQUIRED_GAMES) {
            return $this->activateReferral($referral);
        }

        return false;
    }

    /**
     * Активирует реферала и выдает награды рефереру
     */
    private function activateReferral(Referral $referral): bool
    {
        if ($referral->status !== 'pending') {
            return false;
        }

        $referrer = $referral->referrer;
        $referred = $referral->referred;

        if (!$referrer || !$referred) {
            return false;
        }

        // Выдаем награды рефереру
        $referrer = $this->userService->ensureProfile($referrer);
        $referrerProfile = $referrer->profile;

        $coinsReward = self::BASE_REFERRER_REWARD_COINS;
        $expReward = self::BASE_REFERRER_REWARD_EXP;

        $referrerProfile->coins += $coinsReward;
        $referrerProfile->experience += $expReward;
        $referrerProfile->total_referrals += 1;
        $referrerProfile->save();

        // Обновляем статус реферала
        $referral->status = 'active';
        $referral->activated_at = Carbon::now();
        $referral->rewarded_at = Carbon::now();
        $referral->referrer_coins_earned = $coinsReward;
        $referral->referrer_experience_earned = $expReward;
        $referral->save();

        // Проверяем milestone награды
        $this->checkAndGrantMilestones($referrer);

        $this->logger->info('Реферал активирован', [
            'referrer_id' => $referrer->getKey(),
            'referred_id' => $referred->getKey(),
            'coins' => $coinsReward,
            'exp' => $expReward,
        ]);

        return true;
    }

    /**
     * Выдает начальную награду приглашенному
     */
    private function giveImmediateReward(User $referred, Referral $referral): void
    {
        $referred = $this->userService->ensureProfile($referred);
        $profile = $referred->profile;

        $profile->coins += self::BASE_REFERRED_REWARD_COINS;
        $profile->experience += self::BASE_REFERRED_REWARD_EXP;
        $profile->save();

        $referral->referred_coins_earned = self::BASE_REFERRED_REWARD_COINS;
        $referral->referred_experience_earned = self::BASE_REFERRED_REWARD_EXP;
        $referral->save();
    }

    /**
     * Проверяет и выдает milestone награды
     */
    private function checkAndGrantMilestones(User $referrer): void
    {
        $totalReferrals = $referrer->profile->total_referrals;

        $milestones = ReferralMilestone::where('is_active', true)
            ->where('referrals_count', '<=', $totalReferrals)
            ->get();

        foreach ($milestones as $milestone) {
            // Проверяем, не получал ли уже эту награду
            $alreadyClaimed = $referrer->referralMilestones()
                ->where('milestone_id', $milestone->getKey())
                ->exists();

            if ($alreadyClaimed) {
                continue;
            }

            // Выдаем награду
            $profile = $referrer->profile;
            $profile->coins += $milestone->reward_coins;
            $profile->experience += $milestone->reward_experience;
            $profile->save();

            // Отмечаем как полученную
            $referrer->referralMilestones()->attach($milestone->getKey(), [
                'claimed_at' => Carbon::now(),
            ]);

            $this->logger->info('Получена milestone награда', [
                'user_id' => $referrer->getKey(),
                'milestone' => $milestone->title,
                'referrals' => $totalReferrals,
            ]);
        }
    }

    /**
     * Получает статистику рефералов пользователя
     */
    public function getReferralStats(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        
        $referrals = Referral::where('referrer_user_id', $user->getKey())
            ->with(['referred'])
            ->orderByDesc('created_at')
            ->get();

        $pending = $referrals->where('status', 'pending')->count();
        $active = $referrals->where('status', 'active')->count();
        $totalCoins = $referrals->sum('referrer_coins_earned');
        $totalExp = $referrals->sum('referrer_experience_earned');

        // Следующий milestone
        $currentCount = $user->profile->total_referrals ?? 0;
        $nextMilestone = ReferralMilestone::where('is_active', true)
            ->where('referrals_count', '>', $currentCount)
            ->orderBy('referrals_count')
            ->first();

        return [
            'referral_code' => $user->profile->referral_code ?? $this->generateReferralCode($user),
            'total_referrals' => $active + $pending,
            'active_referrals' => $active,
            'pending_referrals' => $pending,
            'total_coins_earned' => (int)$totalCoins,
            'total_exp_earned' => (int)$totalExp,
            'referrals' => $referrals->filter(function (Referral $ref) {
                return $ref->referred !== null;
            })->map(function (Referral $ref) {
                return [
                    'user' => [
                        'id' => $ref->referred->getKey(),
                        'name' => $ref->referred->first_name ?? 'Пользователь',
                        'username' => $ref->referred->username ?? null,
                    ],
                    'status' => $ref->status,
                    'games_played' => $ref->referred_games_played ?? 0,
                    'created_at' => $ref->created_at ? $ref->created_at->format('d.m.Y') : 'н/д',
                ];
            })->values(),
            'next_milestone' => $nextMilestone ? [
                'title' => $nextMilestone->title,
                'referrals_needed' => $nextMilestone->referrals_count,
                'progress' => $currentCount,
                'reward_coins' => $nextMilestone->reward_coins,
                'reward_experience' => $nextMilestone->reward_experience,
            ] : null,
        ];
    }

    /**
     * Подсчитывает количество игр пользователя
     */
    private function countUserGames(User $user): int
    {
        // Считаем дуэли + режим правда/ложь + другие режимы
        $duelsInitiated = $user->duelsInitiated()->whereIn('status', ['finished', 'cancelled'])->count();
        $duelsOpponent = $user->duelsOpponent()->whereIn('status', ['finished', 'cancelled'])->count();
        $sessions = $user->sessions()->where('state', 'finished')->count();
        
        return $duelsInitiated + $duelsOpponent + $sessions;
    }

    /**
     * Генерирует реферальную ссылку для бота
     */
    public function getReferralLink(User $user): string
    {
        $code = $this->generateReferralCode($user);
        $botUsername = getenv('TELEGRAM_BOT_USERNAME') ?: 'duelquizbot';
        
        return sprintf('https://t.me/%s?start=ref_%s', $botUsername, $code);
    }
}

