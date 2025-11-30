<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryQuestion extends BaseModel
{
    protected $table = 'story_questions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'story_step_id',
        'question_text',
        'context_text',
        'explanation',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(StoryStep::class, 'story_step_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StoryQuestionAnswer::class, 'story_question_id');
    }
}

