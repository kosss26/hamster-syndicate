<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryProgress extends BaseModel
{
    protected $table = 'story_progress';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'chapter_id',
        'current_step_id',
        'status',
        'score',
        'lives_remaining',
        'mistakes',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'int',
        'lives_remaining' => 'int',
        'mistakes' => 'int',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(StoryChapter::class, 'chapter_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(StoryStep::class, 'current_step_id');
    }
}

