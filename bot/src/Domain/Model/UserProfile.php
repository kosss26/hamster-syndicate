<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends BaseModel
{
    protected $table = 'user_profiles';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'level',
        'experience',
        'rating',
        'coins',
        'lives',
        'streak_days',
        'duel_wins',
        'duel_losses',
        'duel_draws',
        'story_progress_score',
        'true_false_record',
        'settings',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'int',
        'experience' => 'int',
        'rating' => 'int',
        'coins' => 'int',
        'lives' => 'int',
        'streak_days' => 'int',
        'duel_wins' => 'int',
        'duel_losses' => 'int',
        'duel_draws' => 'int',
        'story_progress_score' => 'int',
        'true_false_record' => 'int',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

