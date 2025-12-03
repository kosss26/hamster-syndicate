<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStats extends BaseModel
{
    protected $table = 'user_stats';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'total_questions',
        'correct_answers',
        'total_time_ms',
        'best_overall_streak',
        'current_streak',
        'games_played',
        'best_duel_win_streak',
        'answers_by_day',
        'answers_by_hour',
        'last_activity_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_questions' => 'int',
        'correct_answers' => 'int',
        'total_time_ms' => 'int',
        'best_overall_streak' => 'int',
        'current_streak' => 'int',
        'games_played' => 'int',
        'best_duel_win_streak' => 'int',
        'answers_by_day' => 'array',
        'answers_by_hour' => 'array',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить общий процент правильных ответов
     */
    public function getAccuracyPercent(): float
    {
        if ($this->total_questions === 0) {
            return 0.0;
        }
        return round(($this->correct_answers / $this->total_questions) * 100, 1);
    }

    /**
     * Получить среднее время ответа в секундах
     */
    public function getAverageTimeSeconds(): float
    {
        if ($this->total_questions === 0) {
            return 0.0;
        }
        return round(($this->total_time_ms / $this->total_questions) / 1000, 1);
    }

    /**
     * Получить лучший день недели
     */
    public function getBestDay(): ?array
    {
        $dayStats = $this->answers_by_day ?? [];
        
        if (empty($dayStats)) {
            return null;
        }

        $bestDay = null;
        $bestAccuracy = 0;

        foreach ($dayStats as $day => $stats) {
            $total = $stats['total'] ?? 0;
            $correct = $stats['correct'] ?? 0;
            
            if ($total < 5) {
                continue; // Минимум 5 вопросов для статистики
            }

            $accuracy = ($correct / $total) * 100;
            
            if ($accuracy > $bestAccuracy) {
                $bestAccuracy = $accuracy;
                $bestDay = $day;
            }
        }

        if ($bestDay === null) {
            return null;
        }

        return [
            'day' => $bestDay,
            'accuracy' => round($bestAccuracy, 1),
        ];
    }

    /**
     * Получить лучший час
     */
    public function getBestHour(): ?array
    {
        $hourStats = $this->answers_by_hour ?? [];
        
        if (empty($hourStats)) {
            return null;
        }

        $bestHour = null;
        $bestAccuracy = 0;

        foreach ($hourStats as $hour => $stats) {
            $total = $stats['total'] ?? 0;
            $correct = $stats['correct'] ?? 0;
            
            if ($total < 3) {
                continue; // Минимум 3 вопроса для статистики
            }

            $accuracy = ($correct / $total) * 100;
            
            if ($accuracy > $bestAccuracy) {
                $bestAccuracy = $accuracy;
                $bestHour = (int) $hour;
            }
        }

        if ($bestHour === null) {
            return null;
        }

        return [
            'hour' => $bestHour,
            'accuracy' => round($bestAccuracy, 1),
        ];
    }
}

