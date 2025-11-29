<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends BaseModel
{
    protected $table = 'achievements';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'description',
        'points',
        'conditions',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'points' => 'int',
        'conditions' => 'array',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->using(UserAchievement::class)
            ->withPivot(['unlocked_at', 'context'])
            ->withTimestamps();
    }
}

