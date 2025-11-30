<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryStep extends BaseModel
{
    protected $table = 'story_steps';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'chapter_id',
        'code',
        'step_type',
        'narrative_text',
        'position',
        'branch_key',
        'reward_points',
        'penalty_points',
        'transitions',
        'choice_options',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'int',
        'reward_points' => 'int',
        'penalty_points' => 'int',
        'transitions' => 'array',
        'choice_options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPE_NARRATIVE = 'narrative';
    public const TYPE_QUESTION = 'question';
    public const TYPE_CHOICE = 'choice';

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(StoryChapter::class, 'chapter_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(StoryQuestion::class, 'story_step_id');
    }
}

