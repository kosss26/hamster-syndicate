<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelResult extends BaseModel
{
    protected $table = 'duel_results';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'duel_id',
        'winner_user_id',
        'initiator_total_score',
        'opponent_total_score',
        'initiator_correct',
        'opponent_correct',
        'result',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'initiator_total_score' => 'int',
        'opponent_total_score' => 'int',
        'initiator_correct' => 'int',
        'opponent_correct' => 'int',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}

