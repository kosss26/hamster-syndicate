<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends BaseModel
{
    protected $table = 'answers';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'question_id',
        'answer_text',
        'is_correct',
        'score_delta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_correct' => 'bool',
        'score_delta' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

