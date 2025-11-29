<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends BaseModel
{
    protected $table = 'game_sessions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'mode',
        'category_id',
        'story_chapter_id',
        'current_question_id',
        'state',
        'score',
        'correct_count',
        'incorrect_count',
        'streak',
        'payload',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'int',
        'correct_count' => 'int',
        'incorrect_count' => 'int',
        'streak' => 'int',
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

    public function storyChapter(): BelongsTo
    {
        return $this->belongsTo(StoryChapter::class, 'story_chapter_id');
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }
}

