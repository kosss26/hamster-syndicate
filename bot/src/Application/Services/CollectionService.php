<?php

namespace QuizBot\Application\Services;

use Illuminate\Support\Collection as SupportCollection;
use QuizBot\Domain\Model\Collection;
use QuizBot\Domain\Model\CollectionItem;
use QuizBot\Domain\Model\UserCollectionItem;
use QuizBot\Domain\Model\UserProfile;

class CollectionService
{
    private const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary'];

    private const DROP_CONFIG = [
        'duel' => [
            'base_chance' => 0.18,
            'success_bonus' => 0.06,
            'timeout_penalty' => 0.08,
            'prefer_new' => true,
            'rarity_weights' => [
                'common' => 70,
                'rare' => 22,
                'epic' => 7,
                'legendary' => 1,
            ],
        ],
        'truefalse' => [
            'base_chance' => 0.14,
            'success_bonus' => 0.08,
            'streak_step_bonus' => 0.02,
            'streak_step' => 5,
            'max_streak_bonus' => 0.08,
            'prefer_new' => true,
            'rarity_weights' => [
                'common' => 68,
                'rare' => 23,
                'epic' => 8,
                'legendary' => 1,
            ],
        ],
        'lootbox' => [
            'base_chance' => 1.0,
            'prefer_new' => true,
            'rarity_weights' => [
                'common' => 62,
                'rare' => 26,
                'epic' => 10,
                'legendary' => 2,
            ],
        ],
    ];

    private const LOOTBOX_DROP_COUNTS = [
        'bronze' => 1,
        'silver' => 1,
        'gold' => 2,
        'legendary' => 3,
    ];

    private const DUPLICATE_COINS_BY_RARITY = [
        'common' => 25,
        'rare' => 80,
        'epic' => 220,
        'legendary' => 700,
    ];

    private const NEW_CARD_COINS_BY_RARITY = [
        'common' => 10,
        'rare' => 35,
        'epic' => 100,
        'legendary' => 300,
    ];

    private const RARITY_LABELS = [
        'common' => 'Обычная',
        'rare' => 'Редкая',
        'epic' => 'Эпическая',
        'legendary' => 'Легендарная',
    ];

    /**
     * Получить все коллекции
     */
    public function getAll(): array
    {
        return Collection::orderBy('id')->get()->toArray();
    }

    /**
     * Получить коллекции с прогрессом пользователя
     */
    public function getUserCollections(int $userId): array
    {
        $collections = $this->getAll();
        
        $result = [];
        foreach ($collections as $collection) {
            $totalItems = CollectionItem::where('collection_id', $collection['id'])->count();
            $ownedItems = UserCollectionItem::where('user_id', $userId)
                ->whereIn('collection_item_id', function ($query) use ($collection) {
                    $query->select('id')
                        ->from('collection_items')
                        ->where('collection_id', $collection['id']);
                })
                ->count();
            
            $result[] = array_merge($collection, [
                'total_items' => $totalItems,
                'owned_items' => $ownedItems,
                'is_completed' => $totalItems > 0 && $ownedItems === $totalItems,
                'progress_percent' => $totalItems > 0 ? round(($ownedItems / $totalItems) * 100, 1) : 0,
            ]);
        }
        
        return $result;
    }

    /**
     * Получить элементы коллекции
     */
    public function getCollectionItems(int $collectionId): array
    {
        return CollectionItem::where('collection_id', $collectionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    /**
     * Получить элементы коллекции с информацией о владении
     */
    public function getUserCollectionItems(int $userId, int $collectionId): array
    {
        $items = $this->getCollectionItems($collectionId);
        
        // Получаем владение пользователя
        $ownedItems = UserCollectionItem::where('user_id', $userId)
            ->whereIn('collection_item_id', array_column($items, 'id'))
            ->get()
            ->keyBy('collection_item_id');
        
        return array_map(function ($item) use ($ownedItems) {
            $owned = $ownedItems->get($item['id']);
            
            return array_merge($item, [
                'is_owned' => $owned !== null,
                'obtained_at' => $owned ? $owned->obtained_at->format('Y-m-d H:i:s') : null,
                'obtained_from' => $owned ? $owned->obtained_from : null,
            ]);
        }, $items);
    }

    /**
     * Добавить карточку пользователю
     */
    public function addItemToUser(int $userId, int $itemId, string $source = 'lootbox'): ?array
    {
        $item = CollectionItem::find($itemId);
        if (!$item) {
            return null;
        }
        
        // Проверяем, есть ли уже эта карточка
        $existing = UserCollectionItem::where('user_id', $userId)
            ->where('collection_item_id', $itemId)
            ->first();
        
        if ($existing) {
            $rarity = (string) ($item->rarity ?: 'common');
            $duplicateCoins = self::DUPLICATE_COINS_BY_RARITY[$rarity] ?? self::DUPLICATE_COINS_BY_RARITY['common'];
            $profile = UserProfile::where('user_id', $userId)->first();
            if ($profile) {
                $profile->coins += $duplicateCoins;
                $profile->save();
            }

            return [
                'success' => false,
                'message' => 'Эта карточка уже в коллекции',
                'is_duplicate' => true,
                'item' => $this->formatItem($item),
                'rarity_label' => self::RARITY_LABELS[$rarity] ?? self::RARITY_LABELS['common'],
                'duplicate_compensation' => [
                    'coins' => $duplicateCoins,
                ],
            ];
        }
        
        // Добавляем карточку
        $userItem = UserCollectionItem::create([
            'user_id' => $userId,
            'collection_item_id' => $itemId,
            'obtained_at' => now(),
            'obtained_from' => $source,
        ]);
        
        // Проверяем, завершена ли коллекция
        $collection = $item->collection;
        $totalItems = CollectionItem::where('collection_id', $collection->id)->count();
        $ownedItems = UserCollectionItem::where('user_id', $userId)
            ->whereIn('collection_item_id', function ($query) use ($collection) {
                $query->select('id')
                    ->from('collection_items')
                    ->where('collection_id', $collection->id);
            })
            ->count();
        
        $isCollectionCompleted = $totalItems > 0 && $ownedItems === $totalItems;

        $rarity = (string) ($item->rarity ?: 'common');
        $newCardCoins = self::NEW_CARD_COINS_BY_RARITY[$rarity] ?? self::NEW_CARD_COINS_BY_RARITY['common'];
        $profile = UserProfile::where('user_id', $userId)->first();
        if ($profile) {
            $profile->coins += $newCardCoins;
            $profile->save();
        }

        // Если коллекция завершена, выдаём награду
        if ($isCollectionCompleted) {
            if ($profile) {
                if ($collection->reward_coins > 0) {
                    $profile->coins += $collection->reward_coins;
                }
                if ($collection->reward_gems > 0) {
                    $profile->gems += $collection->reward_gems;
                }
                $profile->save();
            }
        }
        
        return [
            'success' => true,
            'item' => $this->formatItem($item),
            'is_duplicate' => false,
            'rarity_label' => self::RARITY_LABELS[$rarity] ?? self::RARITY_LABELS['common'],
            'new_card_bonus' => [
                'coins' => $newCardCoins,
            ],
            'collection_completed' => $isCollectionCompleted,
            'rewards' => $isCollectionCompleted ? [
                'coins' => $collection->reward_coins,
                'gems' => $collection->reward_gems,
            ] : null,
        ];
    }

    /**
     * Выпадение случайной карточки из коллекции
     */
    public function rollRandomItem(int $collectionId): ?CollectionItem
    {
        $items = CollectionItem::where('collection_id', $collectionId)->get();
        
        if ($items->isEmpty()) {
            return null;
        }
        
        // Взвешенная случайность на основе drop_chance
        $totalWeight = $items->sum('drop_chance');
        $random = mt_rand() / mt_getrandmax() * $totalWeight;
        
        $currentWeight = 0;
        foreach ($items as $item) {
            $currentWeight += $item->drop_chance;
            if ($random <= $currentWeight) {
                return $item;
            }
        }
        
        // Fallback на последний элемент
        return $items->last();
    }

    /**
     * Выпадение случайной карточки, которой у пользователя нет
     */
    public function rollNewItem(int $userId, int $collectionId): ?CollectionItem
    {
        // Получаем ID карточек, которые уже есть у пользователя
        $ownedItemIds = UserCollectionItem::where('user_id', $userId)
            ->whereIn('collection_item_id', function ($query) use ($collectionId) {
                $query->select('id')
                    ->from('collection_items')
                    ->where('collection_id', $collectionId);
            })
            ->pluck('collection_item_id')
            ->toArray();
        
        // Получаем карточки, которых нет у пользователя
        $availableItems = CollectionItem::where('collection_id', $collectionId)
            ->whereNotIn('id', $ownedItemIds)
            ->get();
        
        if ($availableItems->isEmpty()) {
            // Если все карточки уже собраны, возвращаем случайную
            return $this->rollRandomItem($collectionId);
        }
        
        // Взвешенная случайность
        $totalWeight = $availableItems->sum('drop_chance');
        $random = mt_rand() / mt_getrandmax() * $totalWeight;
        
        $currentWeight = 0;
        foreach ($availableItems as $item) {
            $currentWeight += $item->drop_chance;
            if ($random <= $currentWeight) {
                return $item;
            }
        }
        
        return $availableItems->last();
    }

    /**
     * Выпадение карточки по событию игры.
     *
     * @param array<string,mixed> $context
     */
    public function awardDropForEvent(int $userId, string $event, array $context = []): ?array
    {
        $config = self::DROP_CONFIG[$event] ?? null;
        if ($config === null) {
            return null;
        }

        $dropChance = (float) ($config['base_chance'] ?? 0.0);
        $isSuccess = (bool) ($context['is_success'] ?? false);
        $isTimeout = (bool) ($context['is_timeout'] ?? false);
        $streak = (int) ($context['streak'] ?? 0);

        if ($isSuccess) {
            $dropChance += (float) ($config['success_bonus'] ?? 0.0);
        }
        if ($isTimeout) {
            $dropChance -= (float) ($config['timeout_penalty'] ?? 0.0);
        }
        if ($streak > 0 && isset($config['streak_step_bonus'], $config['streak_step'])) {
            $steps = intdiv($streak, max(1, (int) $config['streak_step']));
            $dropChance += min(
                (float) ($config['max_streak_bonus'] ?? 0.0),
                $steps * (float) $config['streak_step_bonus']
            );
        }

        $dropChance = max(0.0, min(1.0, $dropChance));
        if (!$this->rollChance($dropChance)) {
            return null;
        }

        $item = $this->pickItemByRarityWeights(
            $userId,
            (array) ($config['rarity_weights'] ?? []),
            (bool) ($config['prefer_new'] ?? true)
        );

        if (!$item instanceof CollectionItem) {
            return null;
        }

        $drop = $this->addItemToUser($userId, (int) $item->getKey(), $event);
        if ($drop === null) {
            return null;
        }

        return array_merge($drop, [
            'event' => $event,
            'drop_chance' => round($dropChance * 100, 2),
        ]);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function awardDropsFromLootbox(int $userId, string $lootboxType): array
    {
        $count = self::LOOTBOX_DROP_COUNTS[$lootboxType] ?? 1;
        $drops = [];

        for ($i = 0; $i < $count; $i++) {
            $drop = $this->awardDropForEvent($userId, 'lootbox', [
                'is_success' => true,
            ]);
            if ($drop !== null) {
                $drop['lootbox_type'] = $lootboxType;
                $drops[] = $drop;
            }
        }

        return $drops;
    }

    /**
     * @param array<string,int|float> $rarityWeights
     */
    private function pickItemByRarityWeights(int $userId, array $rarityWeights, bool $preferNew): ?CollectionItem
    {
        $allItems = CollectionItem::query()->get();
        if ($allItems->isEmpty()) {
            return null;
        }

        $ownedItemIds = UserCollectionItem::query()
            ->where('user_id', $userId)
            ->pluck('collection_item_id')
            ->map(static fn ($id) => (int) $id)
            ->toArray();

        $chosenRarity = $this->pickWeightedKey($rarityWeights) ?? 'common';
        $rarityCandidates = $allItems->filter(function (CollectionItem $item) use ($chosenRarity): bool {
            return (string) ($item->rarity ?: 'common') === $chosenRarity;
        });

        if ($rarityCandidates->isEmpty()) {
            $rarityCandidates = $allItems;
        }

        $candidates = $preferNew
            ? $rarityCandidates->filter(function (CollectionItem $item) use ($ownedItemIds): bool {
                return !in_array((int) $item->getKey(), $ownedItemIds, true);
            })
            : $rarityCandidates;

        if ($candidates->isEmpty()) {
            $candidates = $rarityCandidates;
        }

        return $this->pickWeightedItem($candidates);
    }

    /**
     * @param SupportCollection<int, CollectionItem> $items
     */
    private function pickWeightedItem(SupportCollection $items): ?CollectionItem
    {
        if ($items->isEmpty()) {
            return null;
        }

        $totalWeight = 0.0;
        foreach ($items as $item) {
            $weight = (float) ($item->drop_chance ?? 0.0);
            if ($weight <= 0.0) {
                $weight = 0.01;
            }
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return $items->random();
        }

        $random = (mt_rand() / mt_getrandmax()) * $totalWeight;
        $currentWeight = 0.0;

        foreach ($items as $item) {
            $weight = (float) ($item->drop_chance ?? 0.0);
            if ($weight <= 0.0) {
                $weight = 0.01;
            }
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $item;
            }
        }

        return $items->last();
    }

    /**
     * @param array<string,int|float> $weights
     */
    private function pickWeightedKey(array $weights): ?string
    {
        if ($weights === []) {
            return null;
        }

        $normalized = [];
        $total = 0.0;

        foreach ($weights as $key => $weight) {
            $numericWeight = (float) $weight;
            if ($numericWeight <= 0) {
                continue;
            }
            $normalized[(string) $key] = $numericWeight;
            $total += $numericWeight;
        }

        if ($total <= 0.0) {
            return null;
        }

        $random = (mt_rand() / mt_getrandmax()) * $total;
        $sum = 0.0;

        foreach ($normalized as $key => $weight) {
            $sum += $weight;
            if ($random <= $sum) {
                return $key;
            }
        }

        return array_key_last($normalized);
    }

    private function rollChance(float $chance): bool
    {
        if ($chance <= 0) {
            return false;
        }
        if ($chance >= 1) {
            return true;
        }

        return (mt_rand() / mt_getrandmax()) <= $chance;
    }

    /**
     * @return array<string,mixed>
     */
    private function formatItem(CollectionItem $item): array
    {
        $payload = $item->toArray();
        $rarity = (string) ($item->rarity ?: 'common');
        $payload['rarity'] = in_array($rarity, self::RARITY_ORDER, true) ? $rarity : 'common';
        $payload['rarity_label'] = self::RARITY_LABELS[$payload['rarity']] ?? self::RARITY_LABELS['common'];

        return $payload;
    }
}
