<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\FortuneWheelSpin;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserInventory;
use Illuminate\Support\Carbon;

class FortuneWheelService
{
    private Logger $logger;
    private UserService $userService;

    // Конфигурация секторов колеса (вероятности в процентах)
    private const WHEEL_SECTORS = [
        ['type' => 'coins', 'amount' => 50, 'weight' => 30, 'icon' => '🪙', 'custom_icon_url' => '/api/images/shop/coins.png'],
        ['type' => 'coins', 'amount' => 100, 'weight' => 20, 'icon' => '🪙', 'custom_icon_url' => '/api/images/shop/coins.png'],
        ['type' => 'coins', 'amount' => 200, 'weight' => 10, 'icon' => '🪙', 'custom_icon_url' => '/api/images/shop/coins.png'],
        ['type' => 'exp', 'amount' => 25, 'weight' => 15, 'icon' => '⭐'],
        ['type' => 'life', 'amount' => 1, 'weight' => 10, 'icon' => '❤️'],
        ['type' => 'hint', 'amount' => 1, 'weight' => 10, 'icon' => '💡'],
        ['type' => 'lootbox', 'amount' => 1, 'weight' => 4, 'icon' => '🎁'],
        ['type' => 'gems', 'amount' => 10, 'weight' => 1, 'icon' => '💎'],
    ];

    private const COOLDOWN_HOURS = 3;
    private const PAID_SPIN_COST = 50; // кристаллов

    public function __construct(Logger $logger, UserService $userService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
    }

    /**
     * Проверка доступности бесплатного вращения
     */
    public function canSpinFree(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile->last_wheel_spin) {
            return [
                'can_spin' => true,
                'next_spin_at' => null,
                'hours_left' => 0,
                'minutes_left' => 0,
            ];
        }

        $nextSpinAt = $profile->last_wheel_spin->copy()->addHours(self::COOLDOWN_HOURS);
        $canSpin = Carbon::now()->greaterThanOrEqualTo($nextSpinAt);
        
        if ($canSpin) {
            return [
                'can_spin' => true,
                'next_spin_at' => $nextSpinAt->toIso8601String(),
                'hours_left' => 0,
                'minutes_left' => 0,
            ];
        }
        
        $now = Carbon::now();
        $totalMinutes = $now->diffInMinutes($nextSpinAt, false);
        $hoursLeft = (int) floor(abs($totalMinutes) / 60);
        $minutesLeft = (int) (abs($totalMinutes) % 60);

        return [
            'can_spin' => false,
            'next_spin_at' => $nextSpinAt->toIso8601String(),
            'hours_left' => $hoursLeft,
            'minutes_left' => $minutesLeft,
        ];
    }

    /**
     * Вращение колеса
     */
    public function spin(User $user, bool $usePremium = false): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        // Проверяем доступность
        if (!$usePremium) {
            $canSpin = $this->canSpinFree($user);
            if (!$canSpin['can_spin']) {
                return [
                    'success' => false,
                    'error' => 'Бесплатное вращение ещё недоступно',
                    'hours_left' => $canSpin['hours_left'],
                ];
            }
        } else {
            // Платное вращение - списываем кристаллы
            if ($profile->gems < self::PAID_SPIN_COST) {
                return [
                    'success' => false,
                    'error' => 'Недостаточно кристаллов',
                    'required' => self::PAID_SPIN_COST,
                    'current' => $profile->gems,
                ];
            }
            $profile->gems -= self::PAID_SPIN_COST;
        }

        // Определяем награду
        $reward = $this->determineReward();

        // Проверяем и обновляем streak
        $this->updateStreak($user);

        // Применяем streak бонус (+10% за 7 дней подряд)
        if ($profile->wheel_streak >= 7) {
            $reward['amount'] = (int) ($reward['amount'] * 1.1);
            $reward['streak_bonus'] = true;
        }

        // Выдаем награду
        $this->giveReward($user, $reward);

        // Обновляем время последнего вращения
        if (!$usePremium) {
            $profile->last_wheel_spin = Carbon::now();
        }
        $profile->save();

        // Сохраняем в историю
        FortuneWheelSpin::create([
            'user_id' => $user->getKey(),
            'reward_type' => $reward['type'],
            'reward_amount' => $reward['amount'],
            'is_paid' => $usePremium,
        ]);

        $this->logger->info('Вращение колеса фортуны', [
            'user_id' => $user->getKey(),
            'reward' => $reward,
            'paid' => $usePremium,
            'streak' => $profile->wheel_streak,
        ]);

        return [
            'success' => true,
            'reward' => $reward,
            'streak' => $profile->wheel_streak,
            'next_spin_at' => $usePremium ? null : Carbon::now()->addHours(self::COOLDOWN_HOURS)->toIso8601String(),
            'hours_left' => $usePremium ? 0 : self::COOLDOWN_HOURS,
            'minutes_left' => 0,
        ];
    }

    /**
     * Определение награды с учетом весов
     */
    private function determineReward(): array
    {
        $totalWeight = array_sum(array_column(self::WHEEL_SECTORS, 'weight'));
        $random = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach (self::WHEEL_SECTORS as $sector) {
            $currentWeight += $sector['weight'];
            if ($random <= $currentWeight) {
                return $sector;
            }
        }

        // Fallback (не должно произойти)
        return self::WHEEL_SECTORS[0];
    }

    /**
     * Выдача награды
     */
    private function giveReward(User $user, array $reward): void
    {
        $profile = $user->profile;

        switch ($reward['type']) {
            case 'coins':
                $profile->coins += $reward['amount'];
                break;

            case 'exp':
                $profile->experience += $reward['amount'];
                break;

            case 'life':
                $profile->lives += $reward['amount'];
                break;

            case 'hint':
                $profile->hints += $reward['amount'];
                break;

            case 'gems':
                $profile->gems += $reward['amount'];
                break;

            case 'lootbox':
                // Добавляем бронзовый лутбокс в инвентарь
                $existingItem = UserInventory::where('user_id', $user->getKey())
                    ->where('item_type', 'lootbox')
                    ->where('item_key', 'bronze')
                    ->first();

                if ($existingItem) {
                    $existingItem->quantity += 1;
                    $existingItem->save();
                } else {
                    UserInventory::create([
                        'user_id' => $user->getKey(),
                        'item_type' => 'lootbox',
                        'item_key' => 'bronze',
                        'quantity' => 1,
                        'acquired_at' => Carbon::now(),
                    ]);
                }
                break;
        }

        $profile->save();
    }

    /**
     * Обновление streak (дней подряд)
     */
    private function updateStreak(User $user): void
    {
        $profile = $user->profile;

        if (!$profile->last_wheel_spin) {
            // Первое вращение
            $profile->wheel_streak = 1;
            return;
        }

        $hoursSinceLastSpin = $profile->last_wheel_spin->diffInHours(Carbon::now());

        if ($hoursSinceLastSpin >= 24 && $hoursSinceLastSpin < 48) {
            // Следующий день подряд
            $profile->wheel_streak += 1;
        } elseif ($hoursSinceLastSpin >= 48) {
            // Пропустил день - сброс streak
            $profile->wheel_streak = 1;
        }
        // Если меньше 24 часов - не обновляем streak
    }

    /**
     * Получить статистику вращений
     */
    public function getStats(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        $totalSpins = FortuneWheelSpin::where('user_id', $user->getKey())->count();
        $freeSpins = FortuneWheelSpin::where('user_id', $user->getKey())
            ->where('is_paid', false)
            ->count();

        $history = FortuneWheelSpin::where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $canSpin = $this->canSpinFree($user);

        return [
            'can_spin_free' => $canSpin['can_spin'],
            'next_spin_at' => $canSpin['next_spin_at'],
            'hours_left' => $canSpin['hours_left'],
            'minutes_left' => $canSpin['minutes_left'],
            'wheel_streak' => $profile->wheel_streak,
            'total_spins' => $totalSpins,
            'free_spins' => $freeSpins,
            'paid_spins' => $totalSpins - $freeSpins,
            'history' => $history->map(function (FortuneWheelSpin $spin) {
                return [
                    'reward_type' => $spin->reward_type,
                    'reward_amount' => $spin->reward_amount,
                    'is_paid' => $spin->is_paid,
                    'created_at' => $spin->created_at->format('d.m.Y H:i'),
                ];
            })->toArray(),
        ];
    }

    /**
     * Получить конфигурацию секторов для фронтенда
     */
    public function getWheelConfig(): array
    {
        return self::WHEEL_SECTORS;
    }
}

