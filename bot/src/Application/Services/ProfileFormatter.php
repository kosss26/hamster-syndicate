<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

class ProfileFormatter
{
    private UserService $userService;
    private MessageFormatter $messageFormatter;

    public function __construct(UserService $userService, MessageFormatter $messageFormatter)
    {
        $this->userService = $userService;
        $this->messageFormatter = $messageFormatter;
    }

    /**
     * Рассчитывает опыт, необходимый для следующего уровня
     */
    private function getExperienceForNextLevel(int $currentLevel): int
    {
        // Формула: базовый опыт * уровень^1.5
        return (int) (100 * pow($currentLevel, 1.5));
    }

    public function format(User $user): string
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            throw new \RuntimeException('Не удалось загрузить профиль пользователя.');
        }

        $level = (int) $profile->level;
        $experience = (int) $profile->experience;
        $nextLevelExp = $this->getExperienceForNextLevel($level);
        $currentLevelExp = $this->getExperienceForNextLevel($level - 1);
        $expInCurrentLevel = $experience - $currentLevelExp;
        $expNeeded = $nextLevelExp - $currentLevelExp;

        $duelTotal = (int) ($profile->duel_wins + $profile->duel_losses + $profile->duel_draws);
        $duelWinRate = $duelTotal > 0
            ? round(($profile->duel_wins / $duelTotal) * 100)
            : 0;

        // Заголовок профиля
        $lines = [
            '📊 <b>ТВОЙ ПРОФИЛЬ</b>',
            '',
        ];

        // Уровень и опыт
        $lines[] = sprintf('🎚️ <b>УРОВЕНЬ %d</b>', $level);
        $lines[] = sprintf('⭐ Опыт: %d / %d', $expInCurrentLevel, $expNeeded);
        $lines[] = '━━━━━━━━━━━━━━━━';

        // Основная статистика
        $lines[] = '💎 <b>РЕСУРСЫ</b>';
        $lines[] = sprintf('💰 Монеты: %s', number_format((int) $profile->coins, 0, ',', ' '));
        $lines[] = '━━━━━━━━━━━━━━━━';

        // Статистика дуэлей
        $lines[] = '⚔️ <b>СТАТИСТИКА ДУЭЛЕЙ</b>';
        
        if ($duelTotal > 0) {
            $lines[] = sprintf('📊 Всего дуэлей: %d', $duelTotal);
        }
        
        $lines[] = sprintf('  ✅ Побед: <b>%d</b>', (int) $profile->duel_wins);
        $lines[] = sprintf('  ❌ Поражений: <b>%d</b>', (int) $profile->duel_losses);
        $lines[] = sprintf('  🤝 Ничьих: <b>%d</b>', (int) $profile->duel_draws);
        
        // Серия побед в дуэлях (используем streak_days для хранения серии побед)
        $duelWinStreak = (int) $profile->streak_days;
        $lines[] = sprintf('  🔥 Серия побед: <b>%d</b>', $duelWinStreak);
        
        if ($duelTotal > 0) {
            $winRateEmoji = $duelWinRate >= 70 ? '🔥' : ($duelWinRate >= 50 ? '👍' : '📈');
            $lines[] = sprintf('  %s Win Rate: <b>%d%%</b>', $winRateEmoji, (int) $duelWinRate);
        }

        return implode("\n", $lines);
    }
}

