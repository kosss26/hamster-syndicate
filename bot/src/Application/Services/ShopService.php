<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\ShopItem;
use QuizBot\Domain\Model\ShopPurchase;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserInventory;
use QuizBot\Domain\Model\UserBoost;
use QuizBot\Domain\Model\UserCosmetic;
use Illuminate\Support\Carbon;

class ShopService
{
    private Logger $logger;
    private UserService $userService;
    private ?AchievementTrackerService $achievementTracker;

    public function __construct(Logger $logger, UserService $userService, ?AchievementTrackerService $achievementTracker = null)
    {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->achievementTracker = $achievementTracker;
    }

    /**
     * Получить все товары магазина
     */
    public function getItems(?string $category = null): array
    {
        $query = ShopItem::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_coins');

        if ($category) {
            $query->where('type', $category);
        }

        $items = $query->get();

        return $items->map(function (ShopItem $item) {
            return [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'description' => $item->description,
                'icon' => $item->icon,
                'rarity' => $item->rarity,
                'price_coins' => $item->price_coins,
                'price_gems' => $item->price_gems,
                'metadata' => $item->metadata,
            ];
        })->toArray();
    }

    /**
     * Купить товар
     */
    public function purchase(User $user, int $itemId, int $quantity = 1): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        // Находим товар
        $item = ShopItem::find($itemId);
        if (!$item || !$item->is_active) {
            return [
                'success' => false,
                'error' => 'Товар не найден',
            ];
        }

        // Проверяем количество
        if ($quantity < 1 || $quantity > 99) {
            return [
                'success' => false,
                'error' => 'Неверное количество',
            ];
        }

        // Рассчитываем стоимость
        $totalCoins = $item->price_coins * $quantity;
        $totalGems = $item->price_gems * $quantity;

        // Проверяем достаточность средств
        if ($totalCoins > 0 && $profile->coins < $totalCoins) {
            return [
                'success' => false,
                'error' => 'Недостаточно монет',
                'required' => $totalCoins,
                'current' => $profile->coins,
            ];
        }

        if ($totalGems > 0 && $profile->gems < $totalGems) {
            return [
                'success' => false,
                'error' => 'Недостаточно кристаллов',
                'required' => $totalGems,
                'current' => $profile->gems,
            ];
        }

        // Списываем валюту
        if ($totalCoins > 0) {
            $profile->coins -= $totalCoins;
        }
        if ($totalGems > 0) {
            $profile->gems -= $totalGems;
        }
        $profile->save();

        // Сохраняем покупку в историю
        ShopPurchase::create([
            'user_id' => $user->getKey(),
            'item_id' => $item->id,
            'quantity' => $quantity,
            'price_coins' => $totalCoins,
            'price_gems' => $totalGems,
        ]);

        // Выдаем товар
        $this->giveItem($user, $item, $quantity);

        $this->logger->info('Покупка в магазине', [
            'user_id' => $user->getKey(),
            'item' => $item->name,
            'quantity' => $quantity,
            'coins' => $totalCoins,
            'gems' => $totalGems,
        ]);

        // Трекинг достижений
        if ($this->achievementTracker) {
            try {
                $this->achievementTracker->incrementStat($user->getKey(), 'shop_purchases', $quantity);
                $this->achievementTracker->checkAndUnlock($user->getKey(), ['context' => 'shop_purchase']);
            } catch (\Throwable $e) {
                $this->logger->error('Error tracking shop achievements: ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'item' => $item->name,
            'quantity' => $quantity,
            'coins_spent' => $totalCoins,
            'gems_spent' => $totalGems,
            'coins_left' => $profile->coins,
            'gems_left' => $profile->gems,
        ];
    }

    /**
     * Выдать товар пользователю
     */
    private function giveItem(User $user, ShopItem $item, int $quantity): void
    {
        $profile = $user->profile;
        $metadata = $item->metadata ?? [];

        switch ($item->type) {
            case ShopItem::TYPE_HINT:
                // Подсказки добавляем напрямую в профиль
                $profile->hints += $quantity;
                $profile->save();
                break;

            case ShopItem::TYPE_LIFE:
                // Жизни добавляем в профиль
                $profile->lives += $quantity;
                $profile->save();
                break;

            case ShopItem::TYPE_BOOST:
                // Бусты активируем
                $boostType = $metadata['boost_type'] ?? 'exp_boost';
                $multiplier = $metadata['multiplier'] ?? 1.5;
                $durationHours = $metadata['duration'] ?? 24;

                UserBoost::create([
                    'user_id' => $user->getKey(),
                    'boost_type' => $boostType,
                    'multiplier' => $multiplier,
                    'expires_at' => Carbon::now()->addHours($durationHours),
                ]);
                break;

            case ShopItem::TYPE_COSMETIC:
                // Косметику добавляем в коллекцию
                $cosmeticType = $metadata['cosmetic_type'] ?? 'frame';
                $cosmeticId = $metadata['cosmetic_id'] ?? $item->id;

                UserCosmetic::firstOrCreate([
                    'user_id' => $user->getKey(),
                    'cosmetic_id' => $cosmeticId,
                ], [
                    'cosmetic_type' => $cosmeticType,
                    'rarity' => $item->rarity,
                    'is_equipped' => false,
                    'acquired_at' => Carbon::now(),
                ]);
                break;

            case ShopItem::TYPE_LOOTBOX:
                // Лутбоксы добавляем в инвентарь
                $existingItem = UserInventory::where('user_id', $user->getKey())
                    ->where('item_type', 'lootbox')
                    ->where('item_id', $item->id)
                    ->first();

                if ($existingItem) {
                    $existingItem->quantity += $quantity;
                    $existingItem->save();
                } else {
                    UserInventory::create([
                        'user_id' => $user->getKey(),
                        'item_type' => 'lootbox',
                        'item_id' => $item->id,
                        'item_key' => $metadata['lootbox_type'] ?? 'bronze',
                        'quantity' => $quantity,
                        'acquired_at' => Carbon::now(),
                    ]);
                }
                break;
        }
    }

    /**
     * Получить историю покупок
     */
    public function getPurchaseHistory(User $user, int $limit = 20): array
    {
        $purchases = ShopPurchase::where('user_id', $user->getKey())
            ->with('item')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $purchases->map(function (ShopPurchase $purchase) {
            return [
                'item_name' => $purchase->item->name ?? 'Неизвестный товар',
                'quantity' => $purchase->quantity,
                'price_coins' => $purchase->price_coins,
                'price_gems' => $purchase->price_gems,
                'purchased_at' => $purchase->created_at->format('d.m.Y H:i'),
            ];
        })->toArray();
    }
}

