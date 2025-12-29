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
                'description' => 'Убирает 2 неправильных ответа',
                'icon' => '💡',
                'rarity' => 'common',
                'price_coins' => 100,
                'price_gems' => 0,
                'metadata' => null,
                'sort_order' => 10,
            ],
            [
                'type' => 'hint',
                'name' => 'Пакет 5 подсказок',
                'description' => 'Экономия 20%',
                'icon' => '💡',
                'rarity' => 'uncommon',
                'price_coins' => 400,
                'price_gems' => 0,
                'metadata' => json_encode(['quantity' => 5]),
                'sort_order' => 11,
            ],
            [
                'type' => 'hint',
                'name' => 'Пакет 10 подсказок',
                'description' => 'Экономия 30%',
                'icon' => '💡',
                'rarity' => 'rare',
                'price_coins' => 700,
                'price_gems' => 0,
                'metadata' => json_encode(['quantity' => 10]),
                'sort_order' => 12,
            ],

            // ============= ЖИЗНИ =============
            [
                'type' => 'life',
                'name' => '1 жизнь',
                'description' => 'Дополнительная попытка',
                'icon' => '❤️',
                'rarity' => 'common',
                'price_coins' => 50,
                'price_gems' => 0,
                'metadata' => null,
                'sort_order' => 20,
            ],
            [
                'type' => 'life',
                'name' => '5 жизней',
                'description' => 'Набор жизней',
                'icon' => '❤️',
                'rarity' => 'uncommon',
                'price_coins' => 200,
                'price_gems' => 0,
                'metadata' => json_encode(['quantity' => 5]),
                'sort_order' => 21,
            ],

            // ============= БУСТЫ =============
            [
                'type' => 'boost',
                'name' => 'Буст опыта +50% (24ч)',
                'description' => 'Получай на 50% больше опыта',
                'icon' => '⭐',
                'rarity' => 'rare',
                'price_coins' => 500,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'boost_type' => 'exp_boost',
                    'multiplier' => 1.5,
                    'duration' => 24
                ]),
                'sort_order' => 30,
            ],
            [
                'type' => 'boost',
                'name' => 'Буст монет +50% (24ч)',
                'description' => 'Получай на 50% больше монет',
                'icon' => '🪙',
                'rarity' => 'rare',
                'price_coins' => 500,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'boost_type' => 'coin_boost',
                    'multiplier' => 1.5,
                    'duration' => 24
                ]),
                'sort_order' => 31,
            ],
            [
                'type' => 'boost',
                'name' => 'Мега-буст +100% (24ч)',
                'description' => 'Двойной опыт и монеты!',
                'icon' => '💫',
                'rarity' => 'epic',
                'price_coins' => 0,
                'price_gems' => 100,
                'metadata' => json_encode([
                    'boost_type' => 'both',
                    'multiplier' => 2.0,
                    'duration' => 24
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
                'price_coins' => 300,
                'price_gems' => 0,
                'metadata' => json_encode(['lootbox_type' => 'bronze']),
                'sort_order' => 40,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Серебряный лутбокс',
                'description' => '3-4 награды, шанс на редкие',
                'icon' => '📦',
                'rarity' => 'uncommon',
                'price_coins' => 800,
                'price_gems' => 0,
                'metadata' => json_encode(['lootbox_type' => 'silver']),
                'sort_order' => 41,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Золотой лутбокс',
                'description' => '4-5 наград, гарантированная редкая',
                'icon' => '🎁',
                'rarity' => 'rare',
                'price_coins' => 0,
                'price_gems' => 100,
                'metadata' => json_encode(['lootbox_type' => 'gold']),
                'sort_order' => 42,
            ],
            [
                'type' => 'lootbox',
                'name' => 'Легендарный лутбокс',
                'description' => '5-6 наград, шанс на легендарные',
                'icon' => '💎',
                'rarity' => 'legendary',
                'price_coins' => 0,
                'price_gems' => 500,
                'metadata' => json_encode(['lootbox_type' => 'legendary']),
                'sort_order' => 43,
            ],

            // ============= КОСМЕТИКА - РАМКИ =============
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Огонь"',
                'description' => 'Огненная рамка профиля',
                'icon' => '🔥',
                'rarity' => 'common',
                'price_coins' => 1000,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_fire'
                ]),
                'sort_order' => 50,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Молния"',
                'description' => 'Электрическая рамка',
                'icon' => '⚡',
                'rarity' => 'rare',
                'price_coins' => 2500,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_lightning'
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
                'price_gems' => 150,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_galaxy'
                ]),
                'sort_order' => 52,
            ],
            [
                'type' => 'cosmetic',
                'name' => 'Рамка "Легенда"',
                'description' => 'Только для лучших!',
                'icon' => '👑',
                'rarity' => 'legendary',
                'price_coins' => 0,
                'price_gems' => 500,
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'cosmetic_id' => 'frame_legend'
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
                'price_coins' => 500,
                'price_gems' => 0,
                'metadata' => json_encode([
                    'cosmetic_type' => 'emoji',
                    'cosmetic_id' => 'emoji_basic'
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
                'price_gems' => 100,
                'metadata' => json_encode([
                    'cosmetic_type' => 'emoji',
                    'cosmetic_id' => 'emoji_animated'
                ]),
                'sort_order' => 61,
            ],
        ];

        foreach ($items as $itemData) {
            ShopItem::firstOrCreate(
                ['name' => $itemData['name']],
                $itemData
            );
        }
    }
}

