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
     * Получает звание на основе рейтинга
     */
    public function getRankByRating(int $rating): array
    {
        if ($rating < 400) {
            return ['emoji' => '🥉', 'name' => 'Новичок'];
        } elseif ($rating < 600) {
            return ['emoji' => '📚', 'name' => 'Ученик'];
        } elseif ($rating < 800) {
            return ['emoji' => '📖', 'name' => 'Знаток'];
        } elseif ($rating < 1000) {
            return ['emoji' => '🎓', 'name' => 'Студент'];
        } elseif ($rating < 1200) {
            return ['emoji' => '⭐', 'name' => 'Эксперт'];
        } elseif ($rating < 1400) {
            return ['emoji' => '⭐⭐', 'name' => 'Мастер'];
        } elseif ($rating < 1600) {
            return ['emoji' => '⭐⭐⭐', 'name' => 'Гранд-мастер'];
        } elseif ($rating < 1800) {
            return ['emoji' => '💎', 'name' => 'Элита'];
        } elseif ($rating < 2000) {
            return ['emoji' => '👑', 'name' => 'Легенда'];
        } else {
            return ['emoji' => '🌟', 'name' => 'Иммортал'];
        }
    }

    public function format(User $user): string
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            throw new \RuntimeException('Не удалось загрузить профиль пользователя.');
        }

        $rating = (int) $profile->rating;
        $rank = $this->getRankByRating($rating);

        $duelTotal = (int) ($profile->duel_wins + $profile->duel_losses + $profile->duel_draws);
        $duelWinRate = $duelTotal > 0
            ? round(($profile->duel_wins / $duelTotal) * 100)
            : 0;

        // Заголовок профиля
        $lines = [
            '📊 <b>ТВОЙ ПРОФИЛЬ</b>',
            '',
        ];

        // Звание и рейтинг
        $lines[] = sprintf('%s <b>%s</b>', $rank['emoji'], $rank['name']);
        $lines[] = sprintf('⭐ Рейтинг: <b>%d</b>', $rating);
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

