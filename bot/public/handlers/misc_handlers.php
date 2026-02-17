<?php

declare(strict_types=1);

use QuizBot\Application\Services\UserService;

/**
 * Получение количества игроков онлайн (активность за последние 15 минут)
 */
function handleGetOnline($container, ?array $telegramUser = null): void
{
    $threshold = \Illuminate\Support\Carbon::now()->subMinutes(15);

    $onlineCount = \QuizBot\Domain\Model\User::query()
        ->where('updated_at', '>=', $threshold)
        ->count();

    jsonResponse(['online' => $onlineCount]);
}

/**
 * GET /notifications/admin - активные уведомления от администрации для игроков
 */
function handleGetAdminNotificationsFeed($container, ?array $telegramUser, array $query): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $schema = \Illuminate\Database\Capsule\Manager::schema();
    if (!$schema->hasTable('admin_notifications')) {
        jsonResponse([
            'items' => [],
            'count' => 0,
        ]);
    }

    $limit = max(1, min(50, (int) ($query['limit'] ?? 20)));
    $sinceId = max(0, (int) ($query['since_id'] ?? 0));

    $notificationsQuery = \Illuminate\Database\Capsule\Manager::table('admin_notifications')
        ->where('is_active', true)
        ->orderByDesc('id')
        ->limit($limit);

    if ($sinceId > 0) {
        $notificationsQuery->where('id', '>', $sinceId);
    }

    $items = $notificationsQuery
        ->get(['id', 'title', 'message', 'created_at'])
        ->map(static function ($row): array {
            return [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'message' => (string) $row->message,
                'created_at' => (string) $row->created_at,
                'type' => 'admin_broadcast',
            ];
        })
        ->values();

    jsonResponse([
        'items' => $items,
        'count' => $items->count(),
    ]);
}

/**
 * GET /admin/notifications - список уведомлений для админки
 */
function handleAdminNotificationsList($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $schema = \Illuminate\Database\Capsule\Manager::schema();
    if (!$schema->hasTable('admin_notifications')) {
        jsonResponse([
            'items' => [],
            'count' => 0,
        ]);
    }

    $limit = max(1, min(100, (int) ($query['limit'] ?? 30)));
    $search = trim((string) ($query['q'] ?? ''));

    $notificationsQuery = \Illuminate\Database\Capsule\Manager::table('admin_notifications')
        ->orderByDesc('id')
        ->limit($limit);

    if ($search !== '') {
        $notificationsQuery->where(function ($q) use ($search): void {
            $q->where('title', 'like', '%' . $search . '%')
                ->orWhere('message', 'like', '%' . $search . '%');
        });
    }

    $items = $notificationsQuery
        ->get(['id', 'title', 'message', 'is_active', 'created_by_user_id', 'created_at'])
        ->map(static function ($row): array {
            return [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'message' => (string) $row->message,
                'is_active' => (bool) $row->is_active,
                'created_by_user_id' => $row->created_by_user_id !== null ? (int) $row->created_by_user_id : null,
                'created_at' => (string) $row->created_at,
            ];
        })
        ->values();

    jsonResponse([
        'items' => $items,
        'count' => $items->count(),
    ]);
}

/**
 * POST /admin/notifications/broadcast - отправить уведомление всем игрокам
 */
function handleAdminBroadcastNotification($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $schema = \Illuminate\Database\Capsule\Manager::schema();
    if (!$schema->hasTable('admin_notifications')) {
        jsonError('Таблица уведомлений не создана. Выполните миграции.', 500);
    }

    $title = trim((string) ($body['title'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));

    if ($title === '' || $message === '') {
        jsonError('Укажите заголовок и текст уведомления', 400);
    }

    if (mb_strlen($title) > 160) {
        jsonError('Заголовок слишком длинный (макс. 160 символов)', 400);
    }

    if (mb_strlen($message) > 3000) {
        jsonError('Текст слишком длинный (макс. 3000 символов)', 400);
    }

    $createdByUserId = null;
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    $adminUser = $userService->findByTelegramId((int) ($telegramUser['id'] ?? 0));
    if ($adminUser) {
        $createdByUserId = (int) $adminUser->getKey();
    }

    $id = (int) \Illuminate\Database\Capsule\Manager::table('admin_notifications')->insertGetId([
        'title' => $title,
        'message' => $message,
        'is_active' => true,
        'created_by_user_id' => $createdByUserId,
        'created_at' => \Illuminate\Support\Carbon::now(),
    ]);

    jsonResponse([
        'id' => $id,
        'title' => $title,
        'message' => $message,
        'created_by_user_id' => $createdByUserId,
        'message_text' => 'Рассылка отправлена',
    ]);
}

/**
 * GET /referral/stats - получить реферальную статистику
 */
function handleGetReferralStats($container, ?array $telegramUser): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);

        $user = $userService->findByTelegramId((int) $telegramUser['id']);

        if (!$user) {
            jsonError('Пользователь не найден', 404);
        }

        /** @var \QuizBot\Application\Services\ReferralService $referralService */
        $referralService = $container->get(\QuizBot\Application\Services\ReferralService::class);

        $stats = $referralService->getReferralStats($user);
        $link = $referralService->getReferralLink($user);
        $stats['referral_link'] = $link;

        jsonResponse($stats);
    } catch (\Throwable $e) {
        error_log('Ошибка получения реферальной статистики: ' . $e->getMessage());
        jsonError('Ошибка получения данных', 500);
    }
}

/**
 * POST /support/message - отправить сообщение в поддержку
 */
function handleSupportMessage($container, ?array $telegramUser, array $body): void
{
    if (!$telegramUser) {
        jsonError('Не авторизован', 401);
    }

    $message = trim((string) ($body['message'] ?? ''));
    $topic = trim((string) ($body['topic'] ?? ''));

    if ($message === '') {
        jsonError('Введите сообщение', 400);
    }

    if (mb_strlen($message) < 5) {
        jsonError('Сообщение слишком короткое (минимум 5 символов)', 400);
    }

    if (mb_strlen($message) > 2000) {
        jsonError('Сообщение слишком длинное (максимум 2000 символов)', 400);
    }

    $allowedTopics = [
        'general' => 'Общий вопрос',
        'bug' => 'Ошибка',
        'idea' => 'Пожелание',
        'payment' => 'Покупки',
    ];
    $topicKey = strtolower($topic);
    if (!isset($allowedTopics[$topicKey])) {
        $topicKey = 'general';
    }
    $topicLabel = $allowedTopics[$topicKey];

    try {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        $user = $userService->syncFromTelegram($telegramUser);
        $userService->ensureProfile($user);

        /** @var \QuizBot\Application\Services\AdminService $adminService */
        $adminService = $container->get(\QuizBot\Application\Services\AdminService::class);
        $adminService->sendFeedbackToAdmins(
            $user,
            sprintf("[WebApp][%s]\n%s", $topicLabel, $message)
        );

        jsonResponse([
            'message' => 'Сообщение отправлено. Спасибо за обратную связь!',
        ]);
    } catch (\Throwable $e) {
        error_log('Ошибка отправки сообщения в поддержку: ' . $e->getMessage());
        jsonError('Не удалось отправить сообщение', 500);
    }
}
