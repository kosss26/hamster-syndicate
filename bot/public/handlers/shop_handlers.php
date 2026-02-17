<?php

declare(strict_types=1);

use QuizBot\Application\Services\TicketService;

/**
 * GET /shop/items - получить товары магазина
 */
function handleGetShopItems($container, ?array $telegramUser, ?string $category): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        /** @var TicketService $ticketService */
        $ticketService = $container->get(TicketService::class);
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $ticketState = $ticketService->sync($user);
        $items = $shopService->getItemsForUser($category, $user);
        $profile = $user->profile;
        
        jsonResponse([
            'items' => $items,
            'balance' => [
                'coins' => (int) ($profile->coins ?? 0),
                'gems' => (int) ($profile->gems ?? 0),
                'hints' => (int) ($profile->hints ?? 0),
                'tickets' => (int) ($ticketState['tickets'] ?? 0),
                // legacy alias
                'lives' => (int) ($ticketState['tickets'] ?? 0),
            ],
        ]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения товаров: ' . $e->getMessage());
        jsonError('Ошибка получения товаров', 500);
    }
}

/**
 * POST /shop/purchase - купить товар
 */
function handleShopPurchase($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['item_id'])) {
        jsonError('Не указан ID товара', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $quantity = $body['quantity'] ?? 1;
        $result = $shopService->purchase($user, (int) $body['item_id'], (int) $quantity);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка покупки: ' . $e->getMessage());
        jsonError('Ошибка покупки', 500);
    }
}

/**
 * GET /shop/history - история покупок
 */
function handleShopHistory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $shopService = $container->get(\QuizBot\Application\Services\ShopService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $history = $shopService->getPurchaseHistory($user);
        jsonResponse(['history' => $history]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения истории: ' . $e->getMessage());
        jsonError('Ошибка получения истории', 500);
    }
}

/**
 * GET /inventory - получить инвентарь
 */
function handleGetInventory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $inventory = $inventoryService->getInventory($user);
        jsonResponse($inventory);
    } catch (\Throwable $e) {
        error_log('Ошибка получения инвентаря: ' . $e->getMessage());
        jsonError('Ошибка получения инвентаря', 500);
    }
}

/**
 * POST /inventory/equip - экипировать косметику
 */
function handleEquipCosmetic($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['cosmetic_id'])) {
        jsonError('Не указан ID косметики', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $inventoryService->equipCosmetic($user, (int) $body['cosmetic_id']);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка экипировки: ' . $e->getMessage());
        jsonError('Ошибка экипировки', 500);
    }
}

/**
 * POST /inventory/unequip - снять косметику
 */
function handleUnequipCosmetic($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['cosmetic_type'])) {
        jsonError('Не указан тип косметики', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $inventoryService = $container->get(\QuizBot\Application\Services\InventoryService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $inventoryService->unequipCosmetic($user, $body['cosmetic_type']);
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка снятия косметики: ' . $e->getMessage());
        jsonError('Ошибка снятия косметики', 500);
    }
}

/**
 * GET /wheel/status - статус колеса фортуны
 */
function handleWheelStatus($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $stats = $wheelService->getStats($user);
        jsonResponse($stats);
    } catch (\Throwable $e) {
        error_log('Ошибка получения статуса колеса: ' . $e->getMessage());
        jsonError('Ошибка получения статуса колеса', 500);
    }
}

/**
 * POST /wheel/spin - крутить колесо
 */
function handleWheelSpin($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $usePremium = $body['use_premium'] ?? false;
        $result = $wheelService->spin($user, (bool) $usePremium);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка вращения колеса: ' . $e->getMessage());
        jsonError('Ошибка вращения колеса', 500);
    }
}

/**
 * GET /wheel/config - конфигурация колеса
 */
function handleWheelConfig($container): void
{
    try {
        $wheelService = $container->get(\QuizBot\Application\Services\FortuneWheelService::class);
        $config = $wheelService->getWheelConfig();
        
        jsonResponse(['sectors' => $config]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения конфигурации колеса: ' . $e->getMessage());
        jsonError('Ошибка получения конфигурации', 500);
    }
}

/**
 * POST /lootbox/open - открыть лутбокс
 */
function handleLootboxOpen($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    if (!isset($body['lootbox_type'])) {
        jsonError('Не указан тип лутбокса', 400);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $lootboxService = $container->get(\QuizBot\Application\Services\LootboxService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $result = $lootboxService->openLootbox($user, $body['lootbox_type']);
        
        if (!$result['success']) {
            jsonError($result['error'], 400);
        }
        
        jsonResponse($result);
    } catch (\Throwable $e) {
        error_log('Ошибка открытия лутбокса: ' . $e->getMessage());
        jsonError('Ошибка открытия лутбокса', 500);
    }
}

/**
 * GET /lootbox/config - конфигурация лутбоксов и прогресс гарантов
 */
function handleLootboxConfig($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $lootboxService = $container->get(\QuizBot\Application\Services\LootboxService::class);

        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $config = $lootboxService->getConfig($user);
        jsonResponse($config);
    } catch (\Throwable $e) {
        error_log('Ошибка получения конфига лутбоксов: ' . $e->getMessage());
        jsonError('Ошибка получения конфигурации', 500);
    }
}

/**
 * GET /lootbox/history - история открытых лутбоксов
 */
function handleLootboxHistory($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $lootboxService = $container->get(\QuizBot\Application\Services\LootboxService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $history = $lootboxService->getHistory($user);
        jsonResponse(['history' => $history]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения истории лутбоксов: ' . $e->getMessage());
        jsonError('Ошибка получения истории', 500);
    }
}

/**
 * GET /boosts - получить активные бусты
 */
function handleGetBoosts($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        $userService = $container->get(\QuizBot\Application\Services\UserService::class);
        $boostService = $container->get(\QuizBot\Application\Services\BoostService::class);
        
        $user = $userService->findByTelegramId((int) $telegramUser['id']);
        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        $boosts = $boostService->getActiveBoosts($user);
        jsonResponse(['boosts' => $boosts]);
    } catch (\Throwable $e) {
        error_log('Ошибка получения бустов: ' . $e->getMessage());
        jsonError('Ошибка получения бустов', 500);
    }
}

/**
 * GET /images/{folder}/{filename} - отдача статических изображений
 */
function handleGetImage(string $folder, string $filename): void
{
    // Безопасность: разрешаем только определенные папки
    $allowedFolders = ['shop', 'wheel', 'cosmetics', 'ui'];
    if (!in_array($folder, $allowedFolders)) {
        http_response_code(404);
        exit;
    }
    
    // Очищаем имя файла от потенциально опасных символов
    $filename = basename($filename);
    
    // Исторически файлы могли храниться в двух местах.
    // 1) Новый/основной путь: bot/storage/images
    // 2) Legacy-путь:       bot/public/storage/images
    $candidatePaths = [
        dirname(__DIR__, 2) . '/storage/images/' . $folder . '/' . $filename,
        dirname(__DIR__) . '/storage/images/' . $folder . '/' . $filename,
    ];

    $filepath = null;
    foreach ($candidatePaths as $candidate) {
        if (is_file($candidate)) {
            $filepath = $candidate;
            break;
        }
    }

    // Проверяем существование файла
    if ($filepath === null) {
        http_response_code(404);
        exit;
    }
    
    // Определяем MIME тип
    $mimeType = mime_content_type($filepath);
    
    // Устанавливаем заголовки
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, max-age=86400'); // Кэш на 1 день
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    
    // Отдаем файл
    readfile($filepath);
    exit;
}
