<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnswerHistory extends BaseModel
{
    protected $table = 'user_answer_history';

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'question_id',
        'is_correct',
        'time_ms',
        'mode',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_correct' => 'bool',
        'time_ms' => 'int',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

