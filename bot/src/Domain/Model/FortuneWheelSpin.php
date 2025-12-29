<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class FortuneWheelSpin extends Model
{
    protected $table = 'fortune_wheel_spins';

    protected $fillable = [
        'user_id',
        'reward_type',
        'reward_amount',
        'is_paid',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'reward_amount' => 'integer',
        'is_paid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Пользователь
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

