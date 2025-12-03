<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Carbon\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserAnswerHistory;
use QuizBot\Domain\Model\UserCategoryStats;
use QuizBot\Domain\Model\UserStats;

class StatisticsService
{
    private Logger $logger;

    private const HISTORY_LIMIT = 1000; // Храним последние N ответов

    private const DAY_NAMES = [
        'Mon' => 'Понедельник',
        'Tue' => 'Вторник',
        'Wed' => 'Среда',
        'Thu' => 'Четверг',
        'Fri' => 'Пятница',
        'Sat' => 'Суббота',
        'Sun' => 'Воскресенье',
    ];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Записать ответ пользователя (вызывать при каждом ответе)
     */
    public function recordAnswer(
        User $user,
        ?int $categoryId,
        ?int $questionId,
        bool $isCorrect,
        int $timeMs,
        string $mode
    ): void {
        $now = Carbon::now();

        // Записываем в историю
        UserAnswerHistory::query()->create([
            'user_id' => $user->getKey(),
            'category_id' => $categoryId,
            'question_id' => $questionId,
            'is_correct' => $isCorrect,
            'time_ms' => $timeMs,
            'mode' => $mode,
            'created_at' => $now,
        ]);

        // Обновляем общую статистику
        $this->updateUserStats($user, $isCorrect, $timeMs, $now);

        // Обновляем статистику по категории
        if ($categoryId !== null) {
            $this->updateCategoryStats($user, $categoryId, $isCorrect, $timeMs);
        }

        // Очищаем старую историю
        $this->pruneHistory($user);
    }

    /**
     * Получить полную статистику пользователя
     * 
     * @return array<string, mixed>
     */
    public function getFullStatistics(User $user): array
    {
        $userStats = $this->getOrCreateUserStats($user);
        $categoryStats = $this->getCategoryStats($user);
        $recentActivity = $this->getRecentActivity($user);

        // Сортируем категории по точности
        $sortedCategories = collect($categoryStats)->sortByDesc('accuracy')->values()->all();
        
        // Сильные и слабые стороны
        $strongCategories = collect($sortedCategories)
            ->filter(fn($c) => $c['total'] >= 5)
            ->take(3)
            ->values()
            ->all();
            
        $weakCategories = collect($sortedCategories)
            ->filter(fn($c) => $c['total'] >= 5)
            ->sortBy('accuracy')
            ->take(3)
            ->values()
            ->all();

        // Лучший день и час
        $bestDay = $userStats->getBestDay();
        $bestHour = $userStats->getBestHour();

        return [
            'overview' => [
                'total_questions' => $userStats->total_questions,
                'correct_answers' => $userStats->correct_answers,
                'accuracy' => $userStats->getAccuracyPercent(),
                'average_time' => $userStats->getAverageTimeSeconds(),
                'games_played' => $userStats->games_played,
                'best_streak' => $userStats->best_overall_streak,
                'current_streak' => $userStats->current_streak,
                'best_duel_win_streak' => $userStats->best_duel_win_streak,
            ],
            'categories' => $sortedCategories,
            'strengths' => $strongCategories,
            'weaknesses' => $weakCategories,
            'best_day' => $bestDay ? [
                'day' => $bestDay['day'],
                'day_name' => self::DAY_NAMES[$bestDay['day']] ?? $bestDay['day'],
                'accuracy' => $bestDay['accuracy'],
            ] : null,
            'best_hour' => $bestHour ? [
                'hour' => $bestHour['hour'],
                'hour_formatted' => sprintf('%02d:00', $bestHour['hour']),
                'accuracy' => $bestHour['accuracy'],
            ] : null,
            'activity' => $recentActivity,
            'last_activity' => $userStats->last_activity_at?->toIso8601String(),
        ];
    }

    /**
     * Записать победу/поражение в дуэли (для отслеживания серии)
     */
    public function recordDuelResult(User $user, bool $isWin): void
    {
        $userStats = $this->getOrCreateUserStats($user);

        if ($isWin) {
            $userStats->current_streak++;
            if ($userStats->current_streak > $userStats->best_duel_win_streak) {
                $userStats->best_duel_win_streak = $userStats->current_streak;
            }
        } else {
            $userStats->current_streak = 0;
        }

        $userStats->games_played++;
        $userStats->last_activity_at = Carbon::now();
        $userStats->save();
    }

    /**
     * Получить краткую статистику для отображения в профиле
     * 
     * @return array<string, mixed>
     */
    public function getQuickStats(User $user): array
    {
        $userStats = $this->getOrCreateUserStats($user);
        $categoryStats = $this->getCategoryStats($user);

        $strongestCategory = collect($categoryStats)
            ->filter(fn($c) => $c['total'] >= 5)
            ->sortByDesc('accuracy')
            ->first();

        $weakestCategory = collect($categoryStats)
            ->filter(fn($c) => $c['total'] >= 5)
            ->sortBy('accuracy')
            ->first();

        return [
            'accuracy' => $userStats->getAccuracyPercent(),
            'average_time' => $userStats->getAverageTimeSeconds(),
            'best_streak' => $userStats->best_overall_streak,
            'strongest_category' => $strongestCategory,
            'weakest_category' => $weakestCategory,
            'best_day' => $userStats->getBestDay(),
        ];
    }

    private function updateUserStats(User $user, bool $isCorrect, int $timeMs, Carbon $now): void
    {
        $userStats = $this->getOrCreateUserStats($user);

        $userStats->total_questions++;
        $userStats->total_time_ms += $timeMs;

        if ($isCorrect) {
            $userStats->correct_answers++;
            $userStats->current_streak++;
            if ($userStats->current_streak > $userStats->best_overall_streak) {
                $userStats->best_overall_streak = $userStats->current_streak;
            }
        } else {
            $userStats->current_streak = 0;
        }

        // Обновляем статистику по дням
        $dayKey = $now->format('D');
        $dayStats = $userStats->answers_by_day ?? [];
        if (!isset($dayStats[$dayKey])) {
            $dayStats[$dayKey] = ['total' => 0, 'correct' => 0];
        }
        $dayStats[$dayKey]['total']++;
        if ($isCorrect) {
            $dayStats[$dayKey]['correct']++;
        }
        $userStats->answers_by_day = $dayStats;

        // Обновляем статистику по часам
        $hourKey = $now->format('H');
        $hourStats = $userStats->answers_by_hour ?? [];
        if (!isset($hourStats[$hourKey])) {
            $hourStats[$hourKey] = ['total' => 0, 'correct' => 0];
        }
        $hourStats[$hourKey]['total']++;
        if ($isCorrect) {
            $hourStats[$hourKey]['correct']++;
        }
        $userStats->answers_by_hour = $hourStats;

        $userStats->last_activity_at = $now;
        $userStats->save();
    }

    private function updateCategoryStats(User $user, int $categoryId, bool $isCorrect, int $timeMs): void
    {
        $stats = UserCategoryStats::query()
            ->where('user_id', $user->getKey())
            ->where('category_id', $categoryId)
            ->first();

        if ($stats === null) {
            $stats = new UserCategoryStats([
                'user_id' => $user->getKey(),
                'category_id' => $categoryId,
            ]);
        }

        $stats->total_questions++;
        $stats->total_time_ms += $timeMs;

        if ($isCorrect) {
            $stats->correct_answers++;
        }

        $stats->save();
    }

    private function getOrCreateUserStats(User $user): UserStats
    {
        $stats = UserStats::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($stats === null) {
            $stats = UserStats::query()->create([
                'user_id' => $user->getKey(),
            ]);
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoryStats(User $user): array
    {
        $stats = UserCategoryStats::query()
            ->where('user_id', $user->getKey())
            ->with('category')
            ->get();

        return $stats->map(function (UserCategoryStats $stat) {
            $category = $stat->category;
            return [
                'category_id' => $stat->category_id,
                'category_name' => $category?->title ?? 'Неизвестно',
                'category_icon' => $category?->icon ?? '❓',
                'total' => $stat->total_questions,
                'correct' => $stat->correct_answers,
                'accuracy' => $stat->getAccuracyPercent(),
                'average_time' => $stat->getAverageTimeSeconds(),
                'best_streak' => $stat->best_streak,
            ];
        })->toArray();
    }

    /**
     * Получить активность за последние 7 дней
     * 
     * @return array<string, int>
     */
    private function getRecentActivity(User $user): array
    {
        $startDate = Carbon::now()->subDays(6)->startOfDay();

        $history = UserAnswerHistory::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', $startDate)
            ->get();

        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $activity[$date] = 0;
        }

        foreach ($history as $record) {
            $date = $record->created_at->format('Y-m-d');
            if (isset($activity[$date])) {
                $activity[$date]++;
            }
        }

        return $activity;
    }

    private function pruneHistory(User $user): void
    {
        $count = UserAnswerHistory::query()
            ->where('user_id', $user->getKey())
            ->count();

        if ($count > self::HISTORY_LIMIT) {
            $toDelete = $count - self::HISTORY_LIMIT;
            UserAnswerHistory::query()
                ->where('user_id', $user->getKey())
                ->orderBy('created_at')
                ->limit($toDelete)
                ->delete();
        }
    }
}

