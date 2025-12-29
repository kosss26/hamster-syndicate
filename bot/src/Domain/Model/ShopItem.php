<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    protected $table = 'shop_items';

    protected $fillable = [
        'type',
        'name',
        'description',
        'icon',
        'rarity',
        'price_coins',
        'price_gems',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_coins' => 'integer',
        'price_gems' => 'integer',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Типы товаров
    public const TYPE_HINT = 'hint';
    public const TYPE_LIFE = 'life';
    public const TYPE_BOOST = 'boost';
    public const TYPE_COSMETIC = 'cosmetic';
    public const TYPE_LOOTBOX = 'lootbox';

    // Редкость
    public const RARITY_COMMON = 'common';
    public const RARITY_RARE = 'rare';
    public const RARITY_EPIC = 'epic';
    public const RARITY_LEGENDARY = 'legendary';

    /**
     * История покупок этого товара
     */
    public function purchases()
    {
        return $this->hasMany(ShopPurchase::class, 'item_id');
    }

    /**
     * Получить цену в удобочитаемом формате
     */
    public function getPriceFormatted(): string
    {
        if ($this->price_coins > 0 && $this->price_gems > 0) {
            return "{$this->price_coins}🪙 или {$this->price_gems}💎";
        } elseif ($this->price_coins > 0) {
            return "{$this->price_coins}🪙";
        } else {
            return "{$this->price_gems}💎";
        }
    }
}

