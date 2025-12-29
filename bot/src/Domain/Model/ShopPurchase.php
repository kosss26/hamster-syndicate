<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class ShopPurchase extends Model
{
    protected $table = 'shop_purchases';

    protected $fillable = [
        'user_id',
        'item_id',
        'quantity',
        'price_coins',
        'price_gems',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
        'price_coins' => 'integer',
        'price_gems' => 'integer',
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

    /**
     * Товар
     */
    public function item()
    {
        return $this->belongsTo(ShopItem::class, 'item_id');
    }
}

