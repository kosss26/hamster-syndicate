<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

class DuelGhostPlay extends BaseModel
{
    protected $table = 'duel_ghost_plays';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'snapshot_id',
        'duel_id',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'snapshot_id' => 'integer',
        'duel_id' => 'integer',
        'created_at' => 'datetime',
    ];
}

