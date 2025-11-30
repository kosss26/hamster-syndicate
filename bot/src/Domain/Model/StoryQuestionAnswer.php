<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryQuestionAnswer extends BaseModel
{
    protected $table = 'story_question_answers';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'story_question_id',
        'answer_text',
        'is_correct',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_correct' => 'bool',
        'position' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(StoryQuestion::class, 'story_question_id');
    }
}

