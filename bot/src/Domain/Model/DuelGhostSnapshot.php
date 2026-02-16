<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

class DuelGhostSnapshot extends BaseModel
{
    protected $table = 'duel_ghost_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'source_duel_id',
        'source_user_id',
        'source_rating',
        'question_count',
        'quality_score',
        'rounds_payload',
        'created_at',
    ];

    protected $casts = [
        'source_duel_id' => 'integer',
        'source_user_id' => 'integer',
        'source_rating' => 'integer',
        'question_count' => 'integer',
        'quality_score' => 'integer',
        'rounds_payload' => 'array',
        'created_at' => 'datetime',
    ];
}

