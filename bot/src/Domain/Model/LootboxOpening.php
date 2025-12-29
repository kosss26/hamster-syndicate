<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class LootboxOpening extends Model
{
    protected $table = 'lootbox_openings';

    protected $fillable = [
        'user_id',
        'lootbox_type',
        'rewards',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'rewards' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Типы лутбоксов
    public const TYPE_BRONZE = 'bronze';
    public const TYPE_SILVER = 'silver';
    public const TYPE_GOLD = 'gold';
    public const TYPE_LEGENDARY = 'legendary';

    /**
     * Пользователь
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

