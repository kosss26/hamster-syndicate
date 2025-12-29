<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class UserBoost extends Model
{
    protected $table = 'user_boosts';

    protected $fillable = [
        'user_id',
        'boost_type',
        'multiplier',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'multiplier' => 'float',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Типы бустов
    public const TYPE_EXP = 'exp_boost';
    public const TYPE_COIN = 'coin_boost';

    /**
     * Пользователь
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверка, активен ли буст
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Получить процент бонуса (например, 1.5 → 50%)
     */
    public function getBonusPercent(): int
    {
        return (int) (($this->multiplier - 1) * 100);
    }
}

