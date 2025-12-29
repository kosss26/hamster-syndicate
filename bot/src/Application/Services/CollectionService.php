<?php

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\Collection;
use QuizBot\Domain\Model\CollectionItem;
use QuizBot\Domain\Model\UserCollectionItem;
use QuizBot\Domain\Model\UserProfile;

class CollectionService
{
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
            return [
                'success' => false,
                'message' => 'Эта карточка уже в коллекции',
                'is_duplicate' => true,
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
        
        $isCollectionCompleted = $ownedItems === $totalItems;
        
        // Если коллекция завершена, выдаём награду
        if ($isCollectionCompleted) {
            $profile = UserProfile::where('user_id', $userId)->first();
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
            'item' => $item->toArray(),
            'is_duplicate' => false,
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
}

