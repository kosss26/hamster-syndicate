<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserBoost;
use Illuminate\Support\Carbon;

class BoostService
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Получить активные бусты пользователя
     */
    public function getActiveBoosts(User $user): array
    {
        $boosts = UserBoost::where('user_id', $user->getKey())
            ->where('expires_at', '>', Carbon::now())
            ->get();

        return $boosts->map(function (UserBoost $boost) {
            return [
                'id' => $boost->id,
                'type' => $boost->boost_type,
                'multiplier' => $boost->multiplier,
                'bonus_percent' => $boost->getBonusPercent(),
                'expires_at' => $boost->expires_at->toIso8601String(),
                'hours_left' => (int) Carbon::now()->diffInHours($boost->expires_at, false),
            ];
        })->toArray();
    }

    /**
     * Проверка наличия активного буста
     */
    public function hasBoost(User $user, string $boostType): bool
    {
        return UserBoost::where('user_id', $user->getKey())
            ->where('boost_type', $boostType)
            ->where('expires_at', '>', Carbon::now())
            ->exists();
    }

    /**
     * Получить множитель буста
     */
    public function getMultiplier(User $user, string $boostType): float
    {
        $boost = UserBoost::where('user_id', $user->getKey())
            ->where('boost_type', $boostType)
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('multiplier')
            ->first();

        return $boost ? $boost->multiplier : 1.0;
    }

    /**
     * Применить буст к награде
     */
    public function applyBoost(User $user, string $boostType, int $baseAmount): int
    {
        $multiplier = $this->getMultiplier($user, $boostType);
        return (int) ($baseAmount * $multiplier);
    }

    /**
     * Активировать буст
     */
    public function activateBoost(User $user, string $boostType, float $multiplier, int $durationHours): array
    {
        // Проверяем, нет ли уже активного буста этого типа
        if ($this->hasBoost($user, $boostType)) {
            return [
                'success' => false,
                'error' => 'У вас уже активен буст этого типа',
            ];
        }

        $boost = UserBoost::create([
            'user_id' => $user->getKey(),
            'boost_type' => $boostType,
            'multiplier' => $multiplier,
            'expires_at' => Carbon::now()->addHours($durationHours),
        ]);

        $this->logger->info('Активирован буст', [
            'user_id' => $user->getKey(),
            'boost_type' => $boostType,
            'multiplier' => $multiplier,
            'duration_hours' => $durationHours,
        ]);

        return [
            'success' => true,
            'boost_id' => $boost->id,
            'expires_at' => $boost->expires_at->toIso8601String(),
        ];
    }

    /**
     * Очистка истекших бустов
     */
    public function cleanupExpiredBoosts(): int
    {
        $deleted = UserBoost::where('expires_at', '<', Carbon::now())
            ->delete();

        if ($deleted > 0) {
            $this->logger->info('Очищены истекшие бусты', ['count' => $deleted]);
        }

        return $deleted;
    }
}

