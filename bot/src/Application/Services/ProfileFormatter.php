<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

class ProfileFormatter
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function format(User $user): string
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            throw new \RuntimeException('Не удалось загрузить профиль пользователя.');
        }

        $duelTotal = (int) ($profile->duel_wins + $profile->duel_losses + $profile->duel_draws);
        $duelWinRate = $duelTotal > 0
            ? round(($profile->duel_wins / $duelTotal) * 100)
            : 0;

        $lines = [
            '📊 <b>Твой профиль</b>',
            sprintf('🎚️ Уровень: %d', (int) $profile->level),
            sprintf('🌟 Опыт: %d', (int) $profile->experience),
            sprintf('💰 Монеты: %d', (int) $profile->coins),
            sprintf('🔥 Серия дней: %d', (int) $profile->streak_days),
            sprintf('📖 Очки сюжета: %d', (int) $profile->story_progress_score),
            '',
            '⚔️ <b>Статистика дуэлей</b>',
            sprintf('🏆 Побед: %d', (int) $profile->duel_wins),
            sprintf('💔 Поражений: %d', (int) $profile->duel_losses),
            sprintf('🤝 Ничьих: %d', (int) $profile->duel_draws),
            sprintf('📈 Win Rate: %d%%', (int) $duelWinRate),
        ];

        return implode("\n", $lines);
    }
}

