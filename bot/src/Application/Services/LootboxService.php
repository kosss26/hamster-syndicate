<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\LootboxOpening;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserInventory;
use QuizBot\Domain\Model\UserCosmetic;
use QuizBot\Domain\Model\UserBoost;
use Illuminate\Support\Carbon;

class LootboxService
{
    private Logger $logger;
    private UserService $userService;

    // Конфигурация лутбоксов
    private const LOOTBOX_CONFIG = [
        'bronze' => [
            'rewards_count' => [2, 3], // мин-макс наград
            'rarity_weights' => ['common' => 95, 'uncommon' => 5],
        ],
        'silver' => [
            'rewards_count' => [3, 4],
            'rarity_weights' => ['common' => 75, 'uncommon' => 20, 'rare' => 5],
        ],
        'gold' => [
            'rewards_count' => [4, 5],
            'rarity_weights' => ['uncommon' => 50, 'rare' => 40, 'epic' => 10],
        ],
        'legendary' => [
            'rewards_count' => [5, 6],
            'rarity_weights' => ['rare' => 50, 'epic' => 40, 'legendary' => 10],
        ],
    ];

    // Таблица наград по редкости
    private const REWARDS_POOL = [
        'common' => [
            ['type' => 'coins', 'amount' => [50, 100]],
            ['type' => 'exp', 'amount' => [25, 50]],
            ['type' => 'hint', 'amount' => [1, 2]],
        ],
        'uncommon' => [
            ['type' => 'coins', 'amount' => [200, 300]],
            ['type' => 'exp', 'amount' => [75, 100]],
            ['type' => 'hint', 'amount' => [3, 5]],
            ['type' => 'life', 'amount' => [1, 1]],
        ],
        'rare' => [
            ['type' => 'coins', 'amount' => [500, 700]],
            ['type' => 'exp', 'amount' => [150, 200]],
            ['type' => 'gems', 'amount' => [10, 25]],
            ['type' => 'boost_12h', 'amount' => [1, 1]],
        ],
        'epic' => [
            ['type' => 'coins', 'amount' => [1000, 1500]],
            ['type' => 'exp', 'amount' => [300, 500]],
            ['type' => 'gems', 'amount' => [50, 100]],
            ['type' => 'boost_24h', 'amount' => [1, 1]],
            ['type' => 'cosmetic_epic', 'amount' => [1, 1]],
        ],
        'legendary' => [
            ['type' => 'coins', 'amount' => [2000, 5000]],
            ['type' => 'exp', 'amount' => [500, 1000]],
            ['type' => 'gems', 'amount' => [200, 500]],
            ['type' => 'boost_7d', 'amount' => [1, 1]],
            ['type' => 'cosmetic_legendary', 'amount' => [1, 1]],
        ],
    ];

    public function __construct(Logger $logger, UserService $userService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
    }

    /**
     * Открыть лутбокс
     */
    public function openLootbox(User $user, string $lootboxType): array
    {
        $user = $this->userService->ensureProfile($user);

        // Проверяем наличие лутбокса в инвентаре
        $inventoryItem = UserInventory::where('user_id', $user->getKey())
            ->where('item_type', 'lootbox')
            ->where('item_key', $lootboxType)
            ->first();

        if (!$inventoryItem || $inventoryItem->quantity < 1) {
            return [
                'success' => false,
                'error' => 'У вас нет этого лутбокса',
            ];
        }

        // Генерируем награды
        $rewards = $this->generateRewards($lootboxType);

        // Выдаем награды
        foreach ($rewards as $reward) {
            $this->giveReward($user, $reward);
        }

        // Убираем лутбокс из инвентаря
        $inventoryItem->quantity -= 1;
        if ($inventoryItem->quantity <= 0) {
            $inventoryItem->delete();
        } else {
            $inventoryItem->save();
        }

        // Сохраняем в историю
        LootboxOpening::create([
            'user_id' => $user->getKey(),
            'lootbox_type' => $lootboxType,
            'rewards' => $rewards,
        ]);

        $this->logger->info('Открытие лутбокса', [
            'user_id' => $user->getKey(),
            'lootbox_type' => $lootboxType,
            'rewards_count' => count($rewards),
        ]);

        return [
            'success' => true,
            'lootbox_type' => $lootboxType,
            'rewards' => $rewards,
        ];
    }

    /**
     * Генерация наград
     */
    private function generateRewards(string $lootboxType): array
    {
        $config = self::LOOTBOX_CONFIG[$lootboxType] ?? self::LOOTBOX_CONFIG['bronze'];
        $rewardsCount = mt_rand($config['rewards_count'][0], $config['rewards_count'][1]);

        $rewards = [];
        for ($i = 0; $i < $rewardsCount; $i++) {
            $rarity = $this->determineRarity($config['rarity_weights']);
            $reward = $this->pickRandomReward($rarity);
            $rewards[] = $reward;
        }

        return $rewards;
    }

    /**
     * Определение редкости награды
     */
    private function determineRarity(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($weights as $rarity => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $rarity;
            }
        }

        return 'common';
    }

    /**
     * Выбор случайной награды заданной редкости
     */
    private function pickRandomReward(string $rarity): array
    {
        $pool = self::REWARDS_POOL[$rarity] ?? self::REWARDS_POOL['common'];
        $rewardTemplate = $pool[array_rand($pool)];

        $amount = mt_rand($rewardTemplate['amount'][0], $rewardTemplate['amount'][1]);

        return [
            'type' => $rewardTemplate['type'],
            'amount' => $amount,
            'rarity' => $rarity,
        ];
    }

    /**
     * Выдать награду
     */
    private function giveReward(User $user, array $reward): void
    {
        $profile = $user->profile;

        switch ($reward['type']) {
            case 'coins':
                $profile->coins += $reward['amount'];
                $profile->save();
                break;

            case 'exp':
                $profile->experience += $reward['amount'];
                $profile->save();
                break;

            case 'gems':
                $profile->gems += $reward['amount'];
                $profile->save();
                break;

            case 'hint':
                $profile->hints += $reward['amount'];
                $profile->save();
                break;

            case 'life':
                $profile->lives += $reward['amount'];
                $profile->save();
                break;

            case 'boost_12h':
            case 'boost_24h':
            case 'boost_7d':
                $hours = ['boost_12h' => 12, 'boost_24h' => 24, 'boost_7d' => 168][$reward['type']];
                $boostType = mt_rand(0, 1) ? 'exp_boost' : 'coin_boost';
                
                UserBoost::create([
                    'user_id' => $user->getKey(),
                    'boost_type' => $boostType,
                    'multiplier' => 1.5,
                    'expires_at' => Carbon::now()->addHours($hours),
                ]);
                break;

            case 'cosmetic_epic':
            case 'cosmetic_legendary':
                // Генерируем случайную косметику
                $rarity = $reward['type'] === 'cosmetic_epic' ? 'epic' : 'legendary';
                $cosmeticId = 'frame_' . $rarity . '_' . mt_rand(1, 10);
                
                UserCosmetic::firstOrCreate([
                    'user_id' => $user->getKey(),
                    'cosmetic_id' => $cosmeticId,
                ], [
                    'cosmetic_type' => 'frame',
                    'rarity' => $rarity,
                    'is_equipped' => false,
                    'acquired_at' => Carbon::now(),
                ]);
                break;
        }
    }

    /**
     * Получить историю открытых лутбоксов
     */
    public function getHistory(User $user, int $limit = 10): array
    {
        $history = LootboxOpening::where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $history->map(function (LootboxOpening $opening) {
            return [
                'lootbox_type' => $opening->lootbox_type,
                'rewards' => $opening->rewards,
                'opened_at' => $opening->created_at->format('d.m.Y H:i'),
            ];
        })->toArray();
    }
}

