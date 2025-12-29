<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends BaseModel
{
    protected $table = 'referrals';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code',
        'status',
        'referrer_coins_earned',
        'referrer_experience_earned',
        'referred_coins_earned',
        'referred_experience_earned',
        'referred_completed_onboarding',
        'referred_games_played',
        'activated_at',
        'rewarded_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'referrer_coins_earned' => 'int',
        'referrer_experience_earned' => 'int',
        'referred_coins_earned' => 'int',
        'referred_experience_earned' => 'int',
        'referred_completed_onboarding' => 'bool',
        'referred_games_played' => 'int',
        'activated_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRewarded(): bool
    {
        return $this->status === 'rewarded';
    }
}

