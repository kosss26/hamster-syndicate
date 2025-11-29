<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAchievement extends Pivot
{
    protected $table = 'user_achievements';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unlocked_at' => 'datetime',
        'context' => 'array',
    ];
}

