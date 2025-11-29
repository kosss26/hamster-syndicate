<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Duel extends BaseModel
{
    protected $table = 'duels';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'initiator_user_id',
        'opponent_user_id',
        'category_id',
        'rounds_to_win',
        'status',
        'settings',
        'matched_at',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rounds_to_win' => 'int',
        'settings' => 'array',
        'matched_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(DuelRound::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(DuelResult::class);
    }
}

