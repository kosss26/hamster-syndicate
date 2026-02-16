<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\LootboxOpening;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserBoost;
use QuizBot\Domain\Model\UserCosmetic;
use QuizBot\Domain\Model\UserInventory;

class LootboxService
{
    private Logger $logger;
    private UserService $userService;
    private ?AchievementTrackerService $achievementTracker;
    private ?CollectionService $collectionService;

    /**
     * Комплексная конфигурация лутбоксов:
     * - количество наград
     * - шанс редкости
     * - пороги гаранта (pity)
     * - количество карточных дропов
     */
    private const BOX_CONFIG = [
        'bronze' => [
            'label' => 'Бронзовый',
            'rewards_count' => [2, 3],
            'rarity_weights' => ['common' => 85, 'uncommon' => 13, 'rare' => 2],
            'pity_epic_after' => 14,
            'pity_legendary_after' => 60,
            'card_drops' => 1,
            'duplicate_protection_coins' => 40,
        ],
        'silver' => [
            'label' => 'Серебряный',
            'rewards_count' => [3, 4],
            'rarity_weights' => ['common' => 60, 'uncommon' => 28, 'rare' => 10, 'epic' => 2],
            'pity_epic_after' => 10,
            'pity_legendary_after' => 40,
            'card_drops' => 1,
            'duplicate_protection_coins' => 90,
        ],
        'gold' => [
            'label' => 'Золотой',
            'rewards_count' => [4, 5],
            'rarity_weights' => ['uncommon' => 40, 'rare' => 40, 'epic' => 17, 'legendary' => 3],
            'pity_epic_after' => 6,
            'pity_legendary_after' => 22,
            'card_drops' => 2,
            'duplicate_protection_coins' => 180,
        ],
        'legendary' => [
            'label' => 'Легендарный',
            'rewards_count' => [5, 6],
            'rarity_weights' => ['rare' => 40, 'epic' => 45, 'legendary' => 15],
            'pity_epic_after' => 3,
            'pity_legendary_after' => 10,
            'card_drops' => 3,
            'duplicate_protection_coins' => 350,
        ],
    ];

    /**
     * Пулы наград по редкости.
     * weight определяет вероятность конкретной награды внутри редкости.
     */
    private const REWARD_POOL = [
        'common' => [
            ['type' => 'coins', 'amount' => [80, 170], 'weight' => 42],
            ['type' => 'exp', 'amount' => [35, 80], 'weight' => 25],
            ['type' => 'hint', 'amount' => [1, 2], 'weight' => 20],
            ['type' => 'life', 'amount' => [1, 1], 'weight' => 13],
        ],
        'uncommon' => [
            ['type' => 'coins', 'amount' => [180, 360], 'weight' => 34],
            ['type' => 'exp', 'amount' => [90, 160], 'weight' => 20],
            ['type' => 'hint', 'amount' => [2, 4], 'weight' => 18],
            ['type' => 'life', 'amount' => [1, 2], 'weight' => 18],
            ['type' => 'gems', 'amount' => [4, 10], 'weight' => 10],
        ],
        'rare' => [
            ['type' => 'coins', 'amount' => [420, 760], 'weight' => 32],
            ['type' => 'exp', 'amount' => [170, 300], 'weight' => 18],
            ['type' => 'gems', 'amount' => [12, 28], 'weight' => 22],
            ['type' => 'boost_12h', 'amount' => [1, 1], 'weight' => 14],
            ['type' => 'life', 'amount' => [2, 3], 'weight' => 14],
        ],
        'epic' => [
            ['type' => 'coins', 'amount' => [900, 1600], 'weight' => 25],
            ['type' => 'exp', 'amount' => [320, 580], 'weight' => 15],
            ['type' => 'gems', 'amount' => [40, 90], 'weight' => 25],
            ['type' => 'boost_24h', 'amount' => [1, 1], 'weight' => 18],
            ['type' => 'cosmetic_epic', 'amount' => [1, 1], 'weight' => 10],
            ['type' => 'life', 'amount' => [3, 5], 'weight' => 7],
        ],
        'legendary' => [
            ['type' => 'coins', 'amount' => [2000, 4200], 'weight' => 18],
            ['type' => 'exp', 'amount' => [700, 1300], 'weight' => 14],
            ['type' => 'gems', 'amount' => [120, 260], 'weight' => 24],
            ['type' => 'boost_7d', 'amount' => [1, 1], 'weight' => 20],
            ['type' => 'cosmetic_legendary', 'amount' => [1, 1], 'weight' => 16],
            ['type' => 'life', 'amount' => [5, 8], 'weight' => 8],
        ],
    ];

    private const RARITY_ORDER = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

    public function __construct(
        Logger $logger,
        UserService $userService,
        ?AchievementTrackerService $achievementTracker = null,
        ?CollectionService $collectionService = null
    ) {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->achievementTracker = $achievementTracker;
        $this->collectionService = $collectionService;
    }

    /**
     * Конфиг лутбоксов + прогресс гарантов для UI.
     *
     * @return array<string,mixed>
     */
    public function getConfig(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $settings = $this->getSettings($user);
        $pity = (array) ($settings['lootbox_pity'] ?? []);
        $inventory = $this->getLootboxInventoryMap($user);

        $boxes = [];
        foreach (self::BOX_CONFIG as $type => $cfg) {
            $typePity = (array) ($pity[$type] ?? []);
            $epicMiss = (int) ($typePity['miss_epic'] ?? 0);
            $legendaryMiss = (int) ($typePity['miss_legendary'] ?? 0);
            $epicAfter = max(1, (int) $cfg['pity_epic_after']);
            $legendaryAfter = max(1, (int) $cfg['pity_legendary_after']);

            $boxes[] = [
                'type' => $type,
                'label' => $cfg['label'],
                'rewards_count' => $cfg['rewards_count'],
                'rarity_weights' => $cfg['rarity_weights'],
                'card_drops' => (int) $cfg['card_drops'],
                'inventory_count' => (int) ($inventory[$type] ?? 0),
                'pity' => [
                    'miss_epic' => $epicMiss,
                    'miss_legendary' => $legendaryMiss,
                    'epic_after' => $epicAfter,
                    'legendary_after' => $legendaryAfter,
                    'epic_remaining' => max(0, $epicAfter - $epicMiss),
                    'legendary_remaining' => max(0, $legendaryAfter - $legendaryMiss),
                ],
            ];
        }

        return [
            'boxes' => $boxes,
            'reward_pool' => self::REWARD_POOL,
        ];
    }

    /**
     * Открыть лутбокс.
     *
     * @return array<string,mixed>
     */
    public function openLootbox(User $user, string $lootboxType): array
    {
        $user = $this->userService->ensureProfile($user);
        $type = $this->normalizeLootboxType($lootboxType);
        $config = self::BOX_CONFIG[$type] ?? null;

        if ($config === null) {
            return [
                'success' => false,
                'error' => 'Неизвестный тип лутбокса',
            ];
        }

        $inventoryItem = UserInventory::query()
            ->where('user_id', $user->getKey())
            ->where('item_type', 'lootbox')
            ->where('item_key', $type)
            ->first();

        if (!$inventoryItem || (int) $inventoryItem->quantity < 1) {
            return [
                'success' => false,
                'error' => 'У вас нет этого лутбокса',
            ];
        }

        $settings = $this->getSettings($user);
        $beforePity = $this->buildPityStateForType($type, $settings);
        $generated = $this->generateRewards($type, $settings);
        $rewards = $generated['rewards'];
        $afterPity = $this->buildPityStateForType($type, $settings);

        foreach ($rewards as $reward) {
            $this->giveReward($user, $reward);
        }

        $inventoryItem->quantity = (int) $inventoryItem->quantity - 1;
        if ((int) $inventoryItem->quantity <= 0) {
            $inventoryItem->delete();
        } else {
            $inventoryItem->save();
        }

        $collectionDrops = $this->awardCollectionDrops($user, $type, $config);
        $duplicateProtectionBonus = null;
        if ($collectionDrops !== [] && !$this->hasNewCollectionCard($collectionDrops)) {
            $coinsBonus = (int) ($config['duplicate_protection_coins'] ?? 0);
            if ($coinsBonus > 0 && $user->profile) {
                $user->profile->coins += $coinsBonus;
                $user->profile->save();
                $duplicateProtectionBonus = [
                    'type' => 'coins',
                    'amount' => $coinsBonus,
                    'reason' => 'all_duplicates',
                ];
            }
        }

        LootboxOpening::query()->create([
            'user_id' => $user->getKey(),
            'lootbox_type' => $type,
            'rewards' => [
                'rewards' => $rewards,
                'collection_drops' => $collectionDrops,
                'pity_before' => $beforePity,
                'pity_after' => $afterPity,
                'duplicate_protection_bonus' => $duplicateProtectionBonus,
            ],
        ]);

        $achievementUnlocks = [];
        if ($this->achievementTracker) {
            try {
                $this->achievementTracker->incrementStat($user->getKey(), 'lootbox_openings');
                $achievementUnlocks = $this->achievementTracker->checkAndUnlock($user->getKey(), ['context' => 'lootbox_open']);
            } catch (\Throwable $e) {
                $this->logger->error('Error tracking lootbox achievements: ' . $e->getMessage());
            }
        }

        $settings['lootbox_open_total'] = (int) ($settings['lootbox_open_total'] ?? 0) + 1;
        $openByType = is_array($settings['lootbox_open_by_type'] ?? null) ? $settings['lootbox_open_by_type'] : [];
        $openByType[$type] = (int) ($openByType[$type] ?? 0) + 1;
        $settings['lootbox_open_by_type'] = $openByType;
        $user->profile->settings = $settings;
        $user->profile->save();

        $this->logger->info('Открытие лутбокса', [
            'user_id' => $user->getKey(),
            'lootbox_type' => $type,
            'rewards_count' => count($rewards),
            'collection_drops' => count($collectionDrops),
            'forced_pity' => $generated['forced_pity'],
        ]);

        return [
            'success' => true,
            'lootbox_type' => $type,
            'rewards' => $rewards,
            'collection_drops' => $collectionDrops,
            'duplicate_protection_bonus' => $duplicateProtectionBonus,
            'achievement_unlocks' => $achievementUnlocks,
            'pity_before' => $beforePity,
            'pity_after' => $afterPity,
            'forced_pity' => $generated['forced_pity'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getHistory(User $user, int $limit = 10): array
    {
        $history = LootboxOpening::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $history->map(function (LootboxOpening $opening) {
            $payload = is_array($opening->rewards) ? $opening->rewards : ['rewards' => []];
            $rewards = isset($payload['rewards']) && is_array($payload['rewards']) ? $payload['rewards'] : (is_array($opening->rewards) ? $opening->rewards : []);

            return [
                'lootbox_type' => $opening->lootbox_type,
                'rewards' => $rewards,
                'collection_drops' => is_array($payload['collection_drops'] ?? null) ? $payload['collection_drops'] : [],
                'pity_after' => is_array($payload['pity_after'] ?? null) ? $payload['pity_after'] : null,
                'opened_at' => $opening->created_at->format('d.m.Y H:i'),
            ];
        })->toArray();
    }

    /**
     * @param array<string,mixed> $settings
     * @return array{rewards:array<int,array<string,mixed>>,forced_pity:?string}
     */
    private function generateRewards(string $type, array &$settings): array
    {
        $cfg = self::BOX_CONFIG[$type];
        $rewardsCount = mt_rand((int) $cfg['rewards_count'][0], (int) $cfg['rewards_count'][1]);
        $rewards = [];

        $pity = $this->buildPityStateForType($type, $settings);
        $forcedPity = null;
        if ((int) $pity['miss_legendary'] >= (int) $pity['legendary_after']) {
            $forcedPity = 'legendary';
        } elseif ((int) $pity['miss_epic'] >= (int) $pity['epic_after']) {
            $forcedPity = 'epic';
        }

        for ($i = 0; $i < $rewardsCount; $i++) {
            $minRarity = ($i === 0) ? $forcedPity : null;
            $rarity = $this->determineRarity((array) $cfg['rarity_weights'], $minRarity);
            $rewards[] = $this->pickRandomReward($rarity);
        }

        $highest = $this->getHighestRarity($rewards);
        $this->advancePity($type, $highest, $settings);

        return [
            'rewards' => $rewards,
            'forced_pity' => $forcedPity,
        ];
    }

    /**
     * @param array<string,int|float> $weights
     */
    private function determineRarity(array $weights, ?string $minRarity = null): string
    {
        if ($minRarity !== null) {
            $minIndex = array_search($minRarity, self::RARITY_ORDER, true);
            if ($minIndex !== false) {
                foreach (array_keys($weights) as $rarity) {
                    $index = array_search($rarity, self::RARITY_ORDER, true);
                    if ($index === false || $index < $minIndex) {
                        unset($weights[$rarity]);
                    }
                }
            }
        }

        if ($weights === []) {
            return $minRarity ?: 'common';
        }

        $totalWeight = array_sum($weights);
        $random = mt_rand(1, max(1, (int) $totalWeight));
        $currentWeight = 0;

        foreach ($weights as $rarity => $weight) {
            $currentWeight += (int) $weight;
            if ($random <= $currentWeight) {
                return (string) $rarity;
            }
        }

        return (string) array_key_last($weights);
    }

    /**
     * @return array<string,mixed>
     */
    private function pickRandomReward(string $rarity): array
    {
        $pool = self::REWARD_POOL[$rarity] ?? self::REWARD_POOL['common'];
        $weights = [];
        foreach ($pool as $entry) {
            $weights[] = max(1, (int) ($entry['weight'] ?? 1));
        }
        $index = $this->pickWeightedIndex($weights);
        $rewardTemplate = $pool[$index] ?? $pool[0];
        $amountRange = is_array($rewardTemplate['amount'] ?? null) ? $rewardTemplate['amount'] : [1, 1];
        $amountMin = (int) ($amountRange[0] ?? 1);
        $amountMax = (int) ($amountRange[1] ?? $amountMin);
        $amount = mt_rand(min($amountMin, $amountMax), max($amountMin, $amountMax));

        return [
            'type' => (string) $rewardTemplate['type'],
            'amount' => $amount,
            'rarity' => $rarity,
        ];
    }

    /**
     * @param array<int,int> $weights
     */
    private function pickWeightedIndex(array $weights): int
    {
        $total = array_sum($weights);
        $random = mt_rand(1, max(1, $total));
        $cursor = 0;
        foreach ($weights as $index => $weight) {
            $cursor += $weight;
            if ($random <= $cursor) {
                return (int) $index;
            }
        }

        return max(0, count($weights) - 1);
    }

    /**
     * @param array<int,array<string,mixed>> $rewards
     */
    private function getHighestRarity(array $rewards): string
    {
        $best = 'common';
        $bestIndex = 0;

        foreach ($rewards as $reward) {
            $rarity = (string) ($reward['rarity'] ?? 'common');
            $index = array_search($rarity, self::RARITY_ORDER, true);
            if ($index !== false && $index > $bestIndex) {
                $bestIndex = $index;
                $best = $rarity;
            }
        }

        return $best;
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,int>
     */
    private function buildPityStateForType(string $type, array $settings): array
    {
        $cfg = self::BOX_CONFIG[$type];
        $pity = is_array($settings['lootbox_pity'] ?? null) ? $settings['lootbox_pity'] : [];
        $typePity = is_array($pity[$type] ?? null) ? $pity[$type] : [];

        return [
            'miss_epic' => (int) ($typePity['miss_epic'] ?? 0),
            'miss_legendary' => (int) ($typePity['miss_legendary'] ?? 0),
            'epic_after' => (int) $cfg['pity_epic_after'],
            'legendary_after' => (int) $cfg['pity_legendary_after'],
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function advancePity(string $type, string $highestRarity, array &$settings): void
    {
        $pity = is_array($settings['lootbox_pity'] ?? null) ? $settings['lootbox_pity'] : [];
        $typePity = is_array($pity[$type] ?? null) ? $pity[$type] : [
            'miss_epic' => 0,
            'miss_legendary' => 0,
        ];

        $epicOrBetter = in_array($highestRarity, ['epic', 'legendary'], true);
        $legendary = $highestRarity === 'legendary';

        if ($epicOrBetter) {
            $typePity['miss_epic'] = 0;
        } else {
            $typePity['miss_epic'] = (int) ($typePity['miss_epic'] ?? 0) + 1;
        }

        if ($legendary) {
            $typePity['miss_legendary'] = 0;
        } else {
            $typePity['miss_legendary'] = (int) ($typePity['miss_legendary'] ?? 0) + 1;
        }

        $pity[$type] = $typePity;
        $settings['lootbox_pity'] = $pity;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,array<string,mixed>>
     */
    private function awardCollectionDrops(User $user, string $type, array $config): array
    {
        if (!$this->collectionService) {
            return [];
        }

        $drops = [];
        $count = max(0, (int) ($config['card_drops'] ?? 0));
        for ($i = 0; $i < $count; $i++) {
            try {
                $drop = $this->collectionService->awardDropForEvent((int) $user->getKey(), 'lootbox', [
                    'is_success' => true,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Error rolling collection drop: ' . $e->getMessage());
                $drop = null;
            }

            if (is_array($drop)) {
                $drop['lootbox_type'] = $type;
                $drops[] = $drop;
            }
        }

        return $drops;
    }

    /**
     * @param array<int,array<string,mixed>> $drops
     */
    private function hasNewCollectionCard(array $drops): bool
    {
        foreach ($drops as $drop) {
            if (($drop['is_duplicate'] ?? true) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function getSettings(User $user): array
    {
        $profile = $user->profile;
        if (!$profile) {
            return [];
        }

        return is_array($profile->settings) ? $profile->settings : [];
    }

    /**
     * @return array<string,int>
     */
    private function getLootboxInventoryMap(User $user): array
    {
        $items = UserInventory::query()
            ->where('user_id', $user->getKey())
            ->where('item_type', 'lootbox')
            ->where('quantity', '>', 0)
            ->get();

        $map = [];
        foreach ($items as $item) {
            $key = (string) ($item->item_key ?? '');
            if ($key === '') {
                continue;
            }
            $map[$key] = (int) $item->quantity;
        }

        return $map;
    }

    private function normalizeLootboxType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if (!isset(self::BOX_CONFIG[$normalized])) {
            return 'bronze';
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $reward
     */
    private function giveReward(User $user, array $reward): void
    {
        $profile = $user->profile;
        if (!$profile) {
            return;
        }

        $type = (string) ($reward['type'] ?? '');
        $amount = (int) ($reward['amount'] ?? 0);
        if ($amount < 0) {
            $amount = 0;
        }

        switch ($type) {
            case 'coins':
                $profile->coins += $amount;
                $profile->save();
                return;

            case 'exp':
                // XP сразу учитывает логику уровней.
                $this->userService->grantExperience($user, $amount);
                return;

            case 'gems':
                $profile->gems += $amount;
                $profile->save();
                return;

            case 'hint':
                $profile->hints += $amount;
                $profile->save();
                return;

            case 'life':
                $profile->lives = min(50, (int) $profile->lives + $amount);
                $profile->save();
                return;

            case 'boost_12h':
            case 'boost_24h':
            case 'boost_7d':
                $hoursMap = ['boost_12h' => 12, 'boost_24h' => 24, 'boost_7d' => 168];
                $hours = (int) ($hoursMap[$type] ?? 12);
                $boostType = mt_rand(0, 1) ? 'exp_boost' : 'coin_boost';

                UserBoost::query()->create([
                    'user_id' => $user->getKey(),
                    'boost_type' => $boostType,
                    'multiplier' => 1.5,
                    'expires_at' => Carbon::now()->addHours($hours),
                ]);
                return;

            case 'cosmetic_epic':
            case 'cosmetic_legendary':
                $rarity = $type === 'cosmetic_epic' ? 'epic' : 'legendary';
                $cosmeticId = 'frame_' . $rarity . '_' . mt_rand(1, 24);

                UserCosmetic::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'cosmetic_id' => $cosmeticId,
                ], [
                    'cosmetic_type' => 'frame',
                    'rarity' => $rarity,
                    'is_equipped' => false,
                    'acquired_at' => Carbon::now(),
                ]);
                return;
        }
    }
}

