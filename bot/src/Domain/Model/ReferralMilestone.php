<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReferralMilestone extends BaseModel
{
    protected $table = 'referral_milestones';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'referrals_count',
        'title',
        'description',
        'reward_coins',
        'reward_experience',
        'reward_badge',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'referrals_count' => 'int',
        'reward_coins' => 'int',
        'reward_experience' => 'int',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_referral_milestones')
            ->withPivot(['claimed_at'])
            ->withTimestamps();
    }
}

