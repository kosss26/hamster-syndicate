<?php

declare(strict_types=1);

namespace QuizBot\Database\Seeders;

use QuizBot\Domain\Model\ShopItem;

class ShopItemsSeeder
{
    public function seed(): void
    {
        $items = [
            // ============= ПОДСКАЗКИ =============
            [
                'type' => 'hint',
                'name' => '1 подсказка',
                'description' => 'Быстрый точечный запас на матч',
                'icon' => '💡',
                'rarity' => 'common',
                'price_coins' => 120,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'quantity' => 1,
                    'max_per_purchase' => 6,
                    'daily_limit' => 12,
                ]),
                'sort_order' => 10,
            ],
            [
                'type' => 'hint',
                'name' => 'Пакет 5 подсказок',
                'description' => 'Базовый набор для активной игры',
                'icon' => '💡',
                'rarity' => 'uncommon',
                'price_coins' => 520,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'quantity' => 5,
                    'max_per_purchase' => 3,
                    'daily_limit' => 6,
                    'offer_badge' => 'value',
                ]),
                'sort_order' => 11,
            ],
            [
                'type' => 'hint',
                'name' => 'Пакет 10 подсказок',
                'description' => 'Оптимально для длинных сессий',
                'icon' => '💡',
                'rarity' => 'rare',
                'price_coins' => 950,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'quantity' => 10,
                    'max_per_purchase' => 2,
                    'daily_limit' => 4,
                    'offer_badge' => 'best_value',
                ]),
                'sort_order' => 12,
            ],

            // ============= ЖИЗНИ =============
            [
                'type' => 'life',
                'name' => '1 билет',
                'description' => '1 дуэльный билет',
                'icon' => '🎫',
                'rarity' => 'common',
                'price_coins' => 80,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'quantity' => 1,
                    'max_per_purchase' => 8,
                    'daily_limit' => 16,
                ]),
                'sort_order' => 20,
            ],
            [
                'type' => 'life',
                'name' => '5 билетов',
                'description' => 'Набор дуэльных билетов',
                'icon' => '🎫',
                'rarity' => 'uncommon',
                'price_coins' => 350,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'quantity' => 5,
                    'max_per_purchase' => 4,
                    'daily_limit' => 8,
                    'offer_badge' => 'value',
                ]),
                'sort_order' => 21,
            ],

            // ============= БУСТЫ =============
            [
                'type' => 'boost',
                'name' => 'Буст опыта +50% (24ч)',
                'description' => 'Получай на 50% больше опыта 24 часа',
                'icon' => '⭐',
                'rarity' => 'rare',
                'price_coins' => 900,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'boost_type' => 'exp_boost',
                    'multiplier' => 1.5,
                    'duration' => 24,
                    'max_per_purchase' => 2,
                    'daily_limit' => 2,
                ]),
                'sort_order' => 30,
            ],
            [
                'type' => 'boost',
                'name' => 'Буст монет +50% (24ч)',
                'description' => 'Получай на 50% больше монет 24 часа',
                'icon' => '🪙',
                'rarity' => 'rare',
                'price_coins' => 900,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'boost_type' => 'coin_boost',
                    'multiplier' => 1.5,
                    'duration' => 24,
                    'max_per_purchase' => 2,
                    'daily_limit' => 2,
                ]),
                'sort_order' => 31,
            ],
            [
                'type' => 'boost',
                'name' => 'Мега-буст +100% (24ч)',
                'description' => 'Двойной опыт и монеты на сутки',
                'icon' => '💫',
                'rarity' => 'epic',
                'price_coins' => 0,
                'price_gems' => 180,
                'metadata' => json_encode([
                    'boost_type' => 'both',
                    'multiplier' => 2.0,
                    'duration' => 24,
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                    'offer_badge' => 'premium',
                ]),
                'sort_order' => 32,
            ],

            // ============= ЛУТБОКСЫ =============
            [
                'type' => 'lootbox',
                'name' => 'Бронзовый лутбокс',
                'description' => '2-3 случайные награды',
                'icon' => '📦',
                'rarity' => 'common',
                'price_coins' => 500,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'lootbox_type' => 'bronze',
                    'max_per_purchase' => 4,
                    'daily_limit' => 8,
                ]),
                'sort_order' => 40,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Серебряный лутбокс',
                'description' => '3-4 награды, повышенный шанс на редкие',
                'icon' => '📦',
                'rarity' => 'uncommon',
                'price_coins' => 1100,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'lootbox_type' => 'silver',
                    'max_per_purchase' => 3,
                    'daily_limit' => 5,
                ]),
                'sort_order' => 41,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Золотой лутбокс',
                'description' => '4-5 наград, гарантированная редкая карточка',
                'icon' => '🎁',
                'rarity' => 'rare',
                'price_coins' => 0,
                'price_gems' => 160,
                'metadata' => json_encode([
                    'lootbox_type' => 'gold',
                    'max_per_purchase' => 2,
                    'daily_limit' => 3,
                ]),
                'sort_order' => 42,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Легендарный лутбокс',
                'description' => '5-6 наград, высокий шанс на легендарные',
                'icon' => '💎',
                'rarity' => 'legendary',
                'price_coins' => 0,
                'price_gems' => 420,
                'metadata' => json_encode([
                    'lootbox_type' => 'legendary',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                    'offer_badge' => 'limited',
                ]),
                'sort_order' => 43,
            ],

            // ============= КОСМЕТИКА - РАМКИ =============
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Огонь"',
                'description' => 'Огненная рамка профиля',
                'icon' => '🔥',
                'rarity' => 'common',
                'price_coins' => 1300,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_fire',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 50,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Молния"',
                'description' => 'Электрическая рамка',
                'icon' => '⚡',
                'rarity' => 'rare',
                'price_coins' => 2800,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_lightning',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 51,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Галактика"',
                'description' => 'Космическая рамка',
                'icon' => '🌌',
                'rarity' => 'epic',
                'price_coins' => 0,
                'price_gems' => 220,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_galaxy',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 52,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Легенда"',
                'description' => 'Премиальная рамка для топ-игроков',
                'icon' => '👑',
                'rarity' => 'legendary',
                'price_coins' => 0,
                'price_gems' => 600,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_legend',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 53,
            ],

            // ============= КОСМЕТИКА - ЭМОДЗИ =============
            [
                'type' => 'cosmetic',
                'name' => 'Набор эмодзи "Базовый"',
                'description' => '10 классических эмодзи',
                'icon' => '😎',
                'rarity' => 'common',
                'price_coins' => 700,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'emoji',
                    'cosmetic_id' => 'emoji_basic',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 60,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Анимированные эмодзи',
                'description' => 'Движущиеся эмодзи!',
                'icon' => '🎭',
                'rarity' => 'epic',
                'price_coins' => 0,
                'price_gems' => 180,
                'metadata' => json_encode([
                    'cosmetic_type' => 'emoji',
                    'cosmetic_id' => 'emoji_animated',
                    'max_per_purchase' => 1,
                    'daily_limit' => 1,
                ]),
                'sort_order' => 61,
            ],
        ];

        // Деактивируем устаревшие позиции после переименования "жизни" -> "билеты".
        ShopItem::whereIn('name', ['1 жизнь', '5 жизней'])->update(['is_active' => false]);

        foreach ($items as $itemData) {
            // Добавляем is_active если не указано
            if (!isset($itemData['is_active'])) {
                $itemData['is_active'] = true;
            }
            
            ShopItem::updateOrCreate(
                ['name' => $itemData['name']],
                $itemData
            );
        }
        
        echo "✅ Shop items seeded!\n";
    }
}
