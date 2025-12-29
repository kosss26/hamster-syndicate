<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends BaseModel
{
    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'onboarded_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'telegram_id' => 'int',
        'onboarded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function storyProgress(): HasMany
    {
        return $this->hasMany(StoryProgress::class);
    }

    public function duelsInitiated(): HasMany
    {
        return $this->hasMany(Duel::class, 'initiator_user_id');
    }

    public function duelsOpponent(): HasMany
    {
        return $this->hasMany(Duel::class, 'opponent_user_id');
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->using(UserAchievement::class)
            ->withTimestamps()
            ->withPivot(['unlocked_at', 'context']);
    }

    public function referralsGiven(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referralReceived(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    public function referralMilestones(): BelongsToMany
    {
        return $this->belongsToMany(ReferralMilestone::class, 'user_referral_milestones')
            ->withPivot(['claimed_at'])
            ->withTimestamps();
    }
}

