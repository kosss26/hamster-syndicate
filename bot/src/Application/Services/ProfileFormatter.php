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
            $this->messageFormatter->header('Твой профиль', '📊'),
            '',
        ];

        // Уровень и опыт с прогресс-баром
        $expBar = $this->messageFormatter->progressBar($expInCurrentLevel, $expNeeded, 12);
        $lines[] = sprintf('🎚️ <b>Уровень %d</b>', $level);
        $lines[] = sprintf('🌟 Опыт: %d / %d', $expInCurrentLevel, $expNeeded);
        $lines[] = $expBar;
        $lines[] = '';

        // Основная статистика
        $lines[] = '💎 <b>Ресурсы</b>';
        $lines[] = $this->messageFormatter->formatNumber((int) $profile->coins, '💰');
        $lines[] = sprintf('🔥 Серия: %d дней', (int) $profile->streak_days);
        $lines[] = $this->messageFormatter->formatNumber((int) $profile->story_progress_score, '📖');
        $lines[] = '';

        // Статистика дуэлей
        $lines[] = $this->messageFormatter->header('Статистика дуэлей', '⚔️');
        
        if ($duelTotal > 0) {
            $winRateBar = $this->messageFormatter->progressBar($profile->duel_wins, $duelTotal, 10, '🏆', '⚪');
            $lines[] = sprintf('📊 Всего дуэлей: %d', $duelTotal);
            $lines[] = $winRateBar;
            $lines[] = '';
        }
        
        $lines[] = sprintf('  🏆 Побед: <b>%d</b>', (int) $profile->duel_wins);
        $lines[] = sprintf('  💔 Поражений: <b>%d</b>', (int) $profile->duel_losses);
        $lines[] = sprintf('  🤝 Ничьих: <b>%d</b>', (int) $profile->duel_draws);
        
        if ($duelTotal > 0) {
            $winRateEmoji = $duelWinRate >= 70 ? '🔥' : ($duelWinRate >= 50 ? '👍' : '📈');
            $lines[] = sprintf('  %s Win Rate: <b>%d%%</b>', $winRateEmoji, (int) $duelWinRate);
        }

        return implode("\n", $lines);
    }
}

