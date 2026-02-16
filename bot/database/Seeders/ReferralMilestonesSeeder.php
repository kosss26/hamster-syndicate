<?php

declare(strict_types=1);

namespace QuizBot\Database\Seeders;

use QuizBot\Domain\Model\ReferralMilestone;

class ReferralMilestonesSeeder
{
    public function seed(): void
    {
        $milestones = [
            [
                'referrals_count' => 1,
                'title' => '🌟 Первый друг',
                'description' => 'Пригласил первого друга',
                'reward_coins' => 50,
                'reward_experience' => 25,
                'reward_tickets' => 1,
                'reward_badge' => '🌟',
            ],
            [
                'referrals_count' => 5,
                'title' => '🎯 Наставник',
                'description' => 'Пригласил 5 друзей',
                'reward_coins' => 200,
                'reward_experience' => 100,
                'reward_tickets' => 2,
                'reward_badge' => '🎯',
            ],
            [
                'referrals_count' => 10,
                'title' => '🏅 Рекрутер',
                'description' => 'Пригласил 10 друзей',
                'reward_coins' => 500,
                'reward_experience' => 250,
                'reward_tickets' => 3,
                'reward_badge' => '🏅',
            ],
            [
                'referrals_count' => 25,
                'title' => '👑 Король рефералов',
                'description' => 'Пригласил 25 друзей',
                'reward_coins' => 1500,
                'reward_experience' => 750,
                'reward_tickets' => 5,
                'reward_badge' => '👑',
            ],
            [
                'referrals_count' => 50,
                'title' => '⭐ Легенда',
                'description' => 'Пригласил 50 друзей',
                'reward_coins' => 5000,
                'reward_experience' => 2500,
                'reward_tickets' => 8,
                'reward_badge' => '⭐',
            ],
            [
                'referrals_count' => 100,
                'title' => '🌌 Мастер вселенной',
                'description' => 'Пригласил 100 друзей',
                'reward_coins' => 15000,
                'reward_experience' => 7500,
                'reward_tickets' => 12,
                'reward_badge' => '🌌',
            ],
        ];

        foreach ($milestones as $data) {
            ReferralMilestone::updateOrCreate(
                ['referrals_count' => $data['referrals_count']],
                $data
            );
        }

        echo "✅ Milestone награды реферальной системы созданы\n";
    }
}
