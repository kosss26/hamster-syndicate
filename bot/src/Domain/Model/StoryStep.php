<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryStep extends BaseModel
{
    protected $table = 'story_steps';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'chapter_id',
        'question_id',
        'code',
        'narrative_text',
        'position',
        'branch_key',
        'reward_points',
        'penalty_points',
        'transitions',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'int',
        'reward_points' => 'int',
        'penalty_points' => 'int',
        'transitions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(StoryChapter::class, 'chapter_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}

