<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Duel extends BaseModel
{
    protected $table = 'duels';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_ACTIVE = 'active'; // or 'in_progress'? Migration says 'waiting' default.
    public const STATUS_FINISHED = 'finished';

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

    // Scopes

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeActive($query)
    {
        // Assuming 'matched' means active/in progress
        return $query->whereIn('status', ['matched', 'in_progress']); 
    }

    public function scopeFinished($query)
    {
        return $query->where('status', self::STATUS_FINISHED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('initiator_user_id', $userId)
              ->orWhere('opponent_user_id', $userId);
        });
    }

    /**
     * Active duels for user (either initiator or opponent)
     */
    public function scopeActiveForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('initiator_user_id', $userId)
              ->orWhere('opponent_user_id', $userId);
        })->active();
    }
}

