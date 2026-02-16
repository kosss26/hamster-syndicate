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
        return $this->getItemsForUser($category, null);
    }

    /**
     * Получить товары магазина с учетом пользователя (лимиты/баланс/владение)
     */
    public function getItemsForUser(?string $category = null, ?User $user = null): array
    {
        if ($user !== null) {
            $user = $this->userService->ensureProfile($user);
        }

        $query = ShopItem::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_coins');

        if ($category) {
            $query->where('type', $category);
        }

        $items = $query->get();
        $userContext = $this->buildUserContext($user);

        return $items->map(function (ShopItem $item) use ($user, $userContext) {
            $metadata = is_array($item->metadata) ? $item->metadata : [];
            $unitQuantity = $this->resolveUnitQuantity($metadata);
            $maxPerPurchase = $this->resolvePositiveInt($metadata['max_per_purchase'] ?? null) ?? 99;
            $dailyLimit = $this->resolvePositiveInt($metadata['daily_limit'] ?? null) ?? $this->getDefaultDailyLimit($item->type);
            $purchasedToday = 0;
            $remainingToday = null;
            $isOwned = false;
            $canAfford = null;

            if ($user !== null) {
                if ($dailyLimit !== null) {
                    $purchasedToday = $this->getPurchasedToday($user, (int) $item->id);
                    $remainingToday = max(0, $dailyLimit - $purchasedToday);
                }

                $profile = $user->profile;
                if ($profile !== null) {
                    $canAfford = ($item->price_coins <= 0 || $profile->coins >= $item->price_coins)
                        && ($item->price_gems <= 0 || $profile->gems >= $item->price_gems);
                }

                if ($item->type === ShopItem::TYPE_COSMETIC) {
                    $cosmeticId = (string) ($metadata['cosmetic_id'] ?? '');
                    if ($cosmeticId !== '') {
                        $isOwned = isset($userContext['owned_cosmetics'][$cosmeticId]);
                    }
                }
            }

            $effectiveMaxPerPurchase = $maxPerPurchase;
            if ($remainingToday !== null) {
                $effectiveMaxPerPurchase = min($effectiveMaxPerPurchase, $remainingToday);
            }
            $recommendation = $this->buildRecommendation($item, $metadata, $isOwned, $userContext);

            return [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'description' => $item->description,
                'icon' => $item->icon,
                'rarity' => $item->rarity,
                'price_coins' => $item->price_coins,
                'price_gems' => $item->price_gems,
                'metadata' => $metadata,
                'unit_quantity' => $unitQuantity,
                'max_per_purchase' => $effectiveMaxPerPurchase,
                'daily_limit' => $dailyLimit,
                'purchased_today' => $purchasedToday,
                'remaining_today' => $remainingToday,
                'is_owned' => $isOwned,
                'can_afford' => $canAfford,
                'price_per_unit_coins' => $item->price_coins > 0 ? round($item->price_coins / max(1, $unitQuantity), 2) : null,
                'price_per_unit_gems' => $item->price_gems > 0 ? round($item->price_gems / max(1, $unitQuantity), 2) : null,
                'recommendation_score' => $recommendation['score'],
                'recommendation_tag' => $recommendation['tag'],
                'recommendation_reason' => $recommendation['reason'],
                'is_featured' => $recommendation['score'] >= 75,
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

        $metadata = is_array($item->metadata) ? $item->metadata : [];
        $maxPerPurchase = $this->resolvePositiveInt($metadata['max_per_purchase'] ?? null) ?? 99;

        // Проверяем количество
        if ($quantity < 1 || $quantity > $maxPerPurchase) {
            return [
                'success' => false,
                'error' => sprintf('Можно купить не более %d за раз', $maxPerPurchase),
            ];
        }

        $dailyLimit = $this->resolvePositiveInt($metadata['daily_limit'] ?? null) ?? $this->getDefaultDailyLimit($item->type);
        if ($dailyLimit !== null) {
            $purchasedToday = $this->getPurchasedToday($user, (int) $item->id);
            $remaining = max(0, $dailyLimit - $purchasedToday);
            if ($remaining <= 0) {
                return [
                    'success' => false,
                    'error' => 'Дневной лимит по этому товару исчерпан',
                ];
            }
            if ($quantity > $remaining) {
                return [
                    'success' => false,
                    'error' => sprintf('Можно купить еще максимум %d шт. сегодня', $remaining),
                ];
            }
        }

        if ($item->type === ShopItem::TYPE_COSMETIC) {
            $cosmeticId = (string) ($metadata['cosmetic_id'] ?? '');
            if ($cosmeticId !== '') {
                $alreadyOwned = UserCosmetic::where('user_id', $user->getKey())
                    ->where('cosmetic_id', $cosmeticId)
                    ->exists();
                if ($alreadyOwned) {
                    return [
                        'success' => false,
                        'error' => 'Этот предмет уже есть в вашей коллекции',
                    ];
                }
            }
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
        $grant = $this->giveItem($user, $item, $quantity);

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
            'granted' => $grant,
        ];
    }

    /**
     * Выдать товар пользователю
     */
    private function giveItem(User $user, ShopItem $item, int $quantity): array
    {
        $profile = $user->profile;
        $metadata = is_array($item->metadata) ? $item->metadata : [];
        $unitQuantity = $this->resolveUnitQuantity($metadata);
        $totalUnits = max(1, $quantity) * $unitQuantity;

        switch ($item->type) {
            case ShopItem::TYPE_HINT:
                // Подсказки добавляем напрямую в профиль
                $profile->hints += $totalUnits;
                $profile->save();
                return ['type' => 'hint', 'amount' => $totalUnits];

            case ShopItem::TYPE_LIFE:
                // Билеты добавляем в профиль
                $profile->lives = min(50, (int) $profile->lives + $totalUnits);
                $profile->save();
                return ['type' => 'ticket', 'amount' => $totalUnits];

            case ShopItem::TYPE_BOOST:
                // Бусты активируем
                $boostType = $metadata['boost_type'] ?? 'exp_boost';
                $multiplier = $metadata['multiplier'] ?? 1.5;
                $durationHours = $metadata['duration'] ?? 24;

                $createdBoosts = [];
                $boostTypes = $boostType === 'both' ? ['exp_boost', 'coin_boost'] : [$boostType];
                foreach ($boostTypes as $type) {
                    for ($i = 0; $i < $quantity; $i++) {
                        UserBoost::create([
                            'user_id' => $user->getKey(),
                            'boost_type' => $type,
                            'multiplier' => $multiplier,
                            'expires_at' => Carbon::now()->addHours($durationHours),
                        ]);
                    }
                    $createdBoosts[] = $type;
                }
                return [
                    'type' => 'boost',
                    'boost_types' => $createdBoosts,
                    'multiplier' => $multiplier,
                    'duration_hours' => (int) $durationHours,
                    'amount' => $quantity,
                ];

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
                return ['type' => 'cosmetic', 'cosmetic_id' => (string) $cosmeticId];

            case ShopItem::TYPE_LOOTBOX:
                // Лутбоксы добавляем в инвентарь
                $existingItem = UserInventory::where('user_id', $user->getKey())
                    ->where('item_type', 'lootbox')
                    ->where('item_id', $item->id)
                    ->first();

                if ($existingItem) {
                    $existingItem->quantity += $totalUnits;
                    $existingItem->save();
                } else {
                    UserInventory::create([
                        'user_id' => $user->getKey(),
                        'item_type' => 'lootbox',
                        'item_id' => $item->id,
                        'item_key' => $metadata['lootbox_type'] ?? 'bronze',
                        'quantity' => $totalUnits,
                        'acquired_at' => Carbon::now(),
                    ]);
                }
                return ['type' => 'lootbox', 'amount' => $totalUnits, 'lootbox_type' => $metadata['lootbox_type'] ?? 'bronze'];
        }

        return ['type' => 'unknown', 'amount' => $totalUnits];
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
                'item_id' => (int) $purchase->item_id,
                'item_type' => $purchase->item->type ?? null,
                'item_icon' => $purchase->item->icon ?? '🛍️',
                'item_name' => $purchase->item->name ?? 'Неизвестный товар',
                'quantity' => $purchase->quantity,
                'price_coins' => $purchase->price_coins,
                'price_gems' => $purchase->price_gems,
                'purchased_at' => $purchase->created_at->format('d.m.Y H:i'),
            ];
        })->toArray();
    }

    private function resolveUnitQuantity(array $metadata): int
    {
        $value = $this->resolvePositiveInt($metadata['quantity'] ?? null);
        return $value ?? 1;
    }

    private function resolvePositiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;
        if ($normalized <= 0) {
            return null;
        }

        return $normalized;
    }

    private function buildUserContext(?User $user): array
    {
        if ($user === null || $user->profile === null) {
            return [];
        }

        $profile = $user->profile;
        $ownedCosmetics = UserCosmetic::where('user_id', $user->getKey())
            ->pluck('cosmetic_id')
            ->toArray();

        return [
            'coins' => (int) $profile->coins,
            'gems' => (int) $profile->gems,
            'hints' => (int) $profile->hints,
            'lives' => (int) $profile->lives,
            'has_exp_boost' => UserBoost::where('user_id', $user->getKey())
                ->where('boost_type', 'exp_boost')
                ->where('expires_at', '>', Carbon::now())
                ->exists(),
            'has_coin_boost' => UserBoost::where('user_id', $user->getKey())
                ->where('boost_type', 'coin_boost')
                ->where('expires_at', '>', Carbon::now())
                ->exists(),
            'owned_cosmetics' => array_fill_keys(array_map('strval', $ownedCosmetics), true),
        ];
    }

    private function buildRecommendation(ShopItem $item, array $metadata, bool $isOwned, array $context): array
    {
        if ($isOwned) {
            return ['score' => 0, 'tag' => null, 'reason' => null];
        }

        $score = 40;
        $tag = null;
        $reason = null;

        $hints = (int) ($context['hints'] ?? 0);
        $lives = (int) ($context['lives'] ?? 0);
        $coins = (int) ($context['coins'] ?? 0);
        $gems = (int) ($context['gems'] ?? 0);
        $hasExpBoost = (bool) ($context['has_exp_boost'] ?? false);
        $hasCoinBoost = (bool) ($context['has_coin_boost'] ?? false);

        switch ($item->type) {
            case ShopItem::TYPE_HINT:
                if ($hints <= 1) {
                    $score = 95;
                    $tag = 'critical';
                    $reason = 'Подсказки почти закончились';
                } elseif ($hints <= 3) {
                    $score = 80;
                    $tag = 'recommended';
                    $reason = 'Нужен запас подсказок';
                } else {
                    $score = 55;
                }
                break;
            case ShopItem::TYPE_LIFE:
                if ($lives <= 1) {
                    $score = 92;
                    $tag = 'critical';
                    $reason = 'Мало билетов для серий';
                } elseif ($lives <= 3) {
                    $score = 78;
                    $tag = 'recommended';
                    $reason = 'Пополните билеты заранее';
                } else {
                    $score = 50;
                }
                break;
            case ShopItem::TYPE_BOOST:
                $boostType = (string) ($metadata['boost_type'] ?? '');
                if (($boostType === 'exp_boost' && !$hasExpBoost) || ($boostType === 'coin_boost' && !$hasCoinBoost) || ($boostType === 'both' && (!$hasExpBoost || !$hasCoinBoost))) {
                    $score = 82;
                    $tag = 'hot';
                    $reason = 'Активного буста нет';
                } else {
                    $score = 45;
                }
                break;
            case ShopItem::TYPE_LOOTBOX:
                if ($coins >= 1500 || $gems >= 120) {
                    $score = 76;
                    $tag = 'daily';
                    $reason = 'Можно открыть лутбокс без просадки баланса';
                } else {
                    $score = 52;
                }
                break;
            case ShopItem::TYPE_COSMETIC:
                if ($coins >= (int) $item->price_coins && $gems >= (int) $item->price_gems) {
                    $score = 74;
                    $tag = 'style';
                    $reason = 'Хватит валюты для апгрейда профиля';
                } else {
                    $score = 42;
                }
                break;
        }

        return [
            'score' => max(0, min(100, $score)),
            'tag' => $tag,
            'reason' => $reason,
        ];
    }

    private function getPurchasedToday(User $user, int $itemId): int
    {
        return (int) ShopPurchase::where('user_id', $user->getKey())
            ->where('item_id', $itemId)
            ->whereDate('created_at', Carbon::now()->toDateString())
            ->sum('quantity');
    }

    private function getDefaultDailyLimit(string $itemType): ?int
    {
        switch ($itemType) {
            case ShopItem::TYPE_HINT:
            case ShopItem::TYPE_LIFE:
                return 20;
            case ShopItem::TYPE_BOOST:
                return 3;
            case ShopItem::TYPE_LOOTBOX:
                return 8;
            case ShopItem::TYPE_COSMETIC:
                return 1;
            default:
                return null;
        }
    }
}
