<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelRound extends BaseModel
{
    protected $table = 'duel_rounds';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'duel_id',
        'question_id',
        'round_number',
        'time_limit',
        'initiator_payload',
        'opponent_payload',
        'initiator_score',
        'opponent_score',
        'question_sent_at',
        'closed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'round_number' => 'int',
        'time_limit' => 'int',
        'initiator_payload' => 'array',
        'opponent_payload' => 'array',
        'initiator_score' => 'int',
        'opponent_score' => 'int',
        'question_sent_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

