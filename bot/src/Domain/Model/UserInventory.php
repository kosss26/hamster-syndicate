<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class UserInventory extends Model
{
    protected $table = 'user_inventory';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'item_key',
        'quantity',
        'expires_at',
        'acquired_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'acquired_at' => 'datetime',
    ];

    /**
     * Пользователь
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Товар из магазина
     */
    public function shopItem()
    {
        return $this->belongsTo(ShopItem::class, 'item_id');
    }

    /**
     * Проверка, истек ли срок действия
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

