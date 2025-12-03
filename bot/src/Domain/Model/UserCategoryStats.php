<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCategoryStats extends BaseModel
{
    protected $table = 'user_category_stats';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'total_questions',
        'correct_answers',
        'total_time_ms',
        'best_streak',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_questions' => 'int',
        'correct_answers' => 'int',
        'total_time_ms' => 'int',
        'best_streak' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Получить процент правильных ответов
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
}

