<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserInventory;
use QuizBot\Domain\Model\UserCosmetic;
use Illuminate\Support\Carbon;

class InventoryService
{
    private Logger $logger;
    private UserService $userService;

    public function __construct(Logger $logger, UserService $userService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
    }

    /**
     * Получить инвентарь игрока
     */
    public function getInventory(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        // Получаем предметы из инвентаря
        $items = UserInventory::where('user_id', $user->getKey())
            ->where('quantity', '>', 0)
            ->orderBy('acquired_at', 'desc')
            ->get();

        // Получаем косметику
        $cosmetics = UserCosmetic::where('user_id', $user->getKey())
            ->orderBy('acquired_at', 'desc')
            ->get();

        return [
            'resources' => [
                'coins' => $profile->coins,
                'gems' => $profile->gems,
                'hints' => $profile->hints,
                'lives' => $profile->lives,
            ],
            'items' => $items->map(function (UserInventory $item) {
                return [
                    'id' => $item->id,
                    'type' => $item->item_type,
                    'key' => $item->item_key,
                    'quantity' => $item->quantity,
                    'expires_at' => $item->expires_at?->toIso8601String(),
                    'is_expired' => $item->isExpired(),
                ];
            })->toArray(),
            'cosmetics' => $cosmetics->map(function (UserCosmetic $cosmetic) {
                return [
                    'id' => $cosmetic->id,
                    'type' => $cosmetic->cosmetic_type,
                    'cosmetic_id' => $cosmetic->cosmetic_id,
                    'rarity' => $cosmetic->rarity,
                    'is_equipped' => $cosmetic->is_equipped,
                    'acquired_at' => $cosmetic->acquired_at->format('d.m.Y'),
                ];
            })->toArray(),
        ];
    }

    /**
     * Использовать предмет (например, лутбокс)
     */
    public function useItem(User $user, int $inventoryItemId): array
    {
        $item = UserInventory::find($inventoryItemId);

        if (!$item || $item->user_id !== $user->getKey()) {
            return [
                'success' => false,
                'error' => 'Предмет не найден',
            ];
        }

        if ($item->quantity < 1) {
            return [
                'success' => false,
                'error' => 'Предмет закончился',
            ];
        }

        if ($item->isExpired()) {
            return [
                'success' => false,
                'error' => 'Предмет истёк',
            ];
        }

        // Для лутбоксов - вызываем LootboxService
        // Здесь просто возвращаем информацию, что предмет готов к использованию
        return [
            'success' => true,
            'item_type' => $item->item_type,
            'item_key' => $item->item_key,
        ];
    }

    /**
     * Экипировать косметику
     */
    public function equipCosmetic(User $user, int $cosmeticId): array
    {
        $user = $this->userService->ensureProfile($user);
        $cosmetic = UserCosmetic::find($cosmeticId);

        if (!$cosmetic || $cosmetic->user_id !== $user->getKey()) {
            return [
                'success' => false,
                'error' => 'Косметика не найдена',
            ];
        }

        // Снимаем с других предметов этого типа
        UserCosmetic::where('user_id', $user->getKey())
            ->where('cosmetic_type', $cosmetic->cosmetic_type)
            ->update(['is_equipped' => false]);

        // Экипируем выбранный
        $cosmetic->is_equipped = true;
        $cosmetic->save();

        // Обновляем профиль
        $profile = $user->profile;
        if ($cosmetic->cosmetic_type === 'frame') {
            $profile->equipped_frame = $cosmetic->cosmetic_id;
        } elseif ($cosmetic->cosmetic_type === 'emoji') {
            $profile->equipped_emoji = $cosmetic->cosmetic_id;
        }
        $profile->save();

        $this->logger->info('Экипирована косметика', [
            'user_id' => $user->getKey(),
            'cosmetic' => $cosmetic->cosmetic_id,
        ]);

        return [
            'success' => true,
            'cosmetic_id' => $cosmetic->cosmetic_id,
            'type' => $cosmetic->cosmetic_type,
        ];
    }

    /**
     * Снять косметику
     */
    public function unequipCosmetic(User $user, string $cosmeticType): array
    {
        $user = $this->userService->ensureProfile($user);

        UserCosmetic::where('user_id', $user->getKey())
            ->where('cosmetic_type', $cosmeticType)
            ->update(['is_equipped' => false]);

        $profile = $user->profile;
        if ($cosmeticType === 'frame') {
            $profile->equipped_frame = null;
        } elseif ($cosmeticType === 'emoji') {
            $profile->equipped_emoji = null;
        }
        $profile->save();

        return [
            'success' => true,
            'type' => $cosmeticType,
        ];
    }

    /**
     * Очистка истекших предметов
     */
    public function cleanupExpiredItems(): int
    {
        $deleted = UserInventory::where('expires_at', '<', Carbon::now())
            ->delete();

        $this->logger->info('Очищены истекшие предметы', ['count' => $deleted]);

        return $deleted;
    }
}

