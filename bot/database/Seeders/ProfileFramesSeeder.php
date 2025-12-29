<?php

declare(strict_types=1);

namespace QuizBot\Database\Seeders;

use QuizBot\Domain\Model\ShopItem;

class ProfileFramesSeeder
{
    public function seed(): void
    {
        $frames = [
            // Бесплатные (стартовая)
            [
                'name' => 'Стандартная рамка',
                'description' => 'Базовая рамка профиля',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '🔰',
                'price_coins' => 0,
                'price_gems' => 0,
                'rarity' => 'common',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'default',
                    'gradient' => ['from' => '#6B7280', 'to' => '#4B5563'],
                    'animated' => false,
                ]),
                'is_available' => true,
            ],
            
            // За достижения (будут разблокироваться автоматически)
            [
                'name' => 'Победитель',
                'description' => 'Разблокируется за 10 побед в дуэлях',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '🏆',
                'price_coins' => 0,
                'price_gems' => 0,
                'rarity' => 'uncommon',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'winner',
                    'gradient' => ['from' => '#FBBF24', 'to' => '#D97706'],
                    'animated' => false,
                    'unlock_requirement' => 'duel_wins_10',
                ]),
                'is_available' => true,
            ],
            [
                'name' => 'Серийный игрок',
                'description' => 'Разблокируется за streak 7 дней',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '🔥',
                'price_coins' => 0,
                'price_gems' => 0,
                'rarity' => 'uncommon',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'streak',
                    'gradient' => ['from' => '#F97316', 'to' => '#DC2626'],
                    'animated' => false,
                    'unlock_requirement' => 'streak_7',
                ]),
                'is_available' => true,
            ],
            [
                'name' => 'Легенда',
                'description' => 'Разблокируется за рейтинг 1000+',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '⭐',
                'price_coins' => 0,
                'price_gems' => 0,
                'rarity' => 'rare',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'legend',
                    'gradient' => ['from' => '#8B5CF6', 'to' => '#6D28D9'],
                    'animated' => false,
                    'unlock_requirement' => 'rating_1000',
                ]),
                'is_available' => true,
            ],
            
            // За монеты
            [
                'name' => 'Радужная',
                'description' => 'Яркая многоцветная рамка',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '🌈',
                'price_coins' => 5000,
                'price_gems' => 0,
                'rarity' => 'rare',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'rainbow',
                    'gradient' => ['from' => '#EC4899', 'via' => '#8B5CF6', 'to' => '#3B82F6'],
                    'animated' => false,
                ]),
                'is_available' => true,
            ],
            
            // За кристаллы
            [
                'name' => 'Алмазная',
                'description' => 'Роскошная переливающаяся рамка',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '💎',
                'price_coins' => 0,
                'price_gems' => 500,
                'rarity' => 'epic',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'diamond',
                    'gradient' => ['from' => '#06B6D4', 'via' => '#3B82F6', 'to' => '#8B5CF6'],
                    'animated' => false,
                ]),
                'is_available' => true,
            ],
            [
                'name' => 'Королевская',
                'description' => 'Величественная золотая рамка',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '👑',
                'price_coins' => 0,
                'price_gems' => 1000,
                'rarity' => 'legendary',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'royal',
                    'gradient' => ['from' => '#FDE047', 'via' => '#FACC15', 'to' => '#EAB308'],
                    'animated' => false,
                ]),
                'is_available' => true,
            ],
            [
                'name' => 'Молния',
                'description' => 'Электрическая анимированная рамка',
                'type' => 'cosmetic',
                'category' => 'cosmetic',
                'icon' => '⚡',
                'price_coins' => 0,
                'price_gems' => 200,
                'rarity' => 'rare',
                'metadata' => json_encode([
                    'cosmetic_type' => 'frame',
                    'frame_key' => 'lightning',
                    'gradient' => ['from' => '#FDE047', 'via' => '#A855F7', 'to' => '#3B82F6'],
                    'animated' => true,
                ]),
                'is_available' => true,
            ],
        ];

        foreach ($frames as $frameData) {
            ShopItem::query()->firstOrCreate(
                [
                    'type' => $frameData['type'],
                    'category' => $frameData['category'],
                    'metadata' => $frameData['metadata'],
                ],
                $frameData
            );
        }

        echo "✅ Profile frames seeded!\n";
    }
}

