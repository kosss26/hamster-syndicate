<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260216_000033_add_referral_count_achievements';
    }

    public function up(Builder $schema): void
    {
        if (!$schema->hasTable('achievements')) {
            return;
        }

        $hasCodeColumn = $schema->hasColumn('achievements', 'code');
        $now = date('Y-m-d H:i:s');
        $rows = [
            [
                'key' => 'invite_10_friends',
                'title' => 'Магнит друзей',
                'description' => 'Пригласи 10 друзей',
                'icon' => '🤝',
                'rarity' => 'epic',
                'category' => 'social',
                'condition_type' => 'counter',
                'condition_value' => 10,
                'reward_coins' => 2200,
                'reward_gems' => 20,
                'is_secret' => 0,
                'sort_order' => 42,
            ],
            [
                'key' => 'invite_25_friends',
                'title' => 'Лидер комьюнити',
                'description' => 'Пригласи 25 друзей',
                'icon' => '👑',
                'rarity' => 'legendary',
                'category' => 'social',
                'condition_type' => 'counter',
                'condition_value' => 25,
                'reward_coins' => 6000,
                'reward_gems' => 60,
                'is_secret' => 0,
                'sort_order' => 43,
            ],
        ];

        foreach ($rows as $row) {
            $rowForUpdate = $row;
            if ($hasCodeColumn) {
                $rowForUpdate['code'] = $row['key'];
            }

            $exists = Capsule::table('achievements')->where('key', $row['key'])->exists();
            if ($exists) {
                Capsule::table('achievements')->where('key', $row['key'])->update($rowForUpdate);
                continue;
            }

            $insert = $rowForUpdate;
            $insert['created_at'] = $now;
            Capsule::table('achievements')->insert($insert);
        }
    }
};
