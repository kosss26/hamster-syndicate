<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates\Handlers;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StoryService;
use QuizBot\Application\Services\AdminService;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\Duel;
use QuizBot\Presentation\Updates\Handlers\Concerns\SendsDuelMessages;

final class CommandHandler
{
    use SendsDuelMessages;

    private ClientInterface $telegramClient;

    private Logger $logger;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private StoryService $storyService;

    private ProfileFormatter $profileFormatter;

    private AdminService $adminService;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter,
        AdminService $adminService
    ) {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->profileFormatter = $profileFormatter;
        $this->adminService = $adminService;
    }

    protected function getTelegramClient(): ClientInterface
    {
        return $this->telegramClient;
    }

    protected function getLogger(): Logger
    {
        return $this->logger;
    }

    protected function getDuelService(): DuelService
    {
        return $this->duelService;
    }

    /**
     * @param array<string, mixed> $command
     */
    public function handle(array $command): void
    {
        $chatId = $command['chat_id'] ?? null;
        $commandText = $command['command'] ?? null;

        if ($chatId === null || $commandText === null) {
            $this->logger->warning('Некорректная команда', $command);

            return;
        }

        $user = $this->resolveUser($command);

        $normalized = strtolower($commandText);

        if ($this->startsWith($normalized, '/start')) {
            $this->sendStart($chatId);

            return;
        }

        if ($this->startsWith($normalized, '/story')) {
            $this->sendStoryMenu($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/play')) {
            $this->sendCategoryMenu($chatId);

            return;
        }

        if ($this->startsWith($normalized, '/duel')) {
            $this->handleDuel($chatId, $commandText, $user);

            return;
        }

        if ($this->startsWith($normalized, '/profile')) {
            $this->sendProfile($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/help')) {
            $this->sendHelp($chatId);

            return;
        }

        if ($this->startsWith($normalized, '/admin')) {
            $this->handleAdmin($chatId, $user);

            return;
        }

        $this->sendUnknown($chatId);
    }

    /**
     * @param int|string $chatId
     */
    private function sendStart($chatId): void
    {
        $text = implode("\n", [
            '🌟 Добро пожаловать в «Путешествие знаний»!',
            'Готовы проверять эрудицию? Выбирайте режим — сюжет, свободная игра или дуэль.',
            'Команды:',
            '<b>/story</b> — сюжетное приключение.',
            '<b>/play</b> — быстрые раунды по категориям.',
            '<b>/duel</b> — дуэли 1 на 1.',
            '<b>/profile</b> — статистика и прогресс.',
            '<b>/help</b> — помощь.',
        ]);

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendStoryMenu($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось определить профиль. Нажми /start, чтобы синхронизировать данные и открыть сюжет.',
                ],
            ]);

            return;
        }

        $entries = $this->storyService->getChaptersForUser($user);

        if (empty($entries)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "🎭 Сюжет пока в разработке.\nСледи за обновлениями — новые главы уже на подходе!",
                ],
            ]);

            return;
        }

        $buttons = [];
        $completedCount = 0;

        foreach ($entries as $index => $entry) {
            $chapter = $entry['chapter'];
            $status = $entry['status'];
            $position = $chapter->position ?: ($index + 1);

            $prefix = match ($status) {
                StoryService::STATUS_COMPLETED => '✅ ',
                StoryService::STATUS_IN_PROGRESS => '🟡 ',
                StoryService::STATUS_AVAILABLE => '🟢 ',
                default => '🔒 ',
            };

            if ($status === StoryService::STATUS_COMPLETED) {
                $completedCount++;
            }

            $callbackData = $status === StoryService::STATUS_LOCKED
                ? 'story-locked:' . $chapter->code
                : 'story:' . $chapter->code;

            $buttons[] = [[
                'text' => sprintf('%sГлава %d: %s', $prefix, $position, $chapter->title),
                'callback_data' => $callbackData,
            ]];
        }

        $lines = [
            '🎭 <b>Сюжетное путешествие</b>',
            'Глава за главой: новые эпизоды открываются после прохождения предыдущих.',
            '',
            'Легенда:',
            '🟢 доступно • 🟡 в процессе • ✅ пройдено • 🔒 закрыто',
            '',
            sprintf('Пройдено глав: %d из %d', $completedCount, count($entries)),
        ];

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $buttons,
                ],
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendCategoryMenu($chatId): void
    {
        $categories = [
            ['code' => 'history', 'title' => '📜 История'],
            ['code' => 'science_tech', 'title' => '🧪 Наука и технологии'],
            ['code' => 'culture', 'title' => '🎬 Поп-культура'],
            ['code' => 'geo', 'title' => '🌍 География'],
            ['code' => 'sport', 'title' => '🥇 Спорт'],
            ['code' => 'nature', 'title' => '🌱 Природа'],
        ];

        $keyboard = array_chunk(
            array_map(
                fn (array $category) => [
                    'text' => $category['title'],
                    'callback_data' => 'play:' . $category['code'],
                ],
                $categories
            ),
            2
        );

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => "🎯 <b>Свободная игра</b>\nВыберите категорию:",
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $keyboard,
                ],
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendDuelMenu($chatId, ?Duel $duel): void
    {
        if ($duel !== null) {
            $statusText = $this->formatDuelStatus($duel);
            $text = implode("\n", [
                '⚔️ <b>Твоя дуэль</b>',
                $statusText,
                '',
                'Пригласи друга: нажми «👥 Пригласить друга», затем отправь его ник в формате @username.',
                'Или выбери «🎲 Случайный соперник», чтобы найти игрока автоматически.',
            ]);
        } else {
            $text = implode("\n", [
                '⚔️ <b>Дуэль</b>',
                'Новая дуэль создана! Нажми «👥 Пригласить друга» и отправь ник соперника @username.',
                'Также можно воспользоваться режимом «🎲 Случайный соперник».',
            ]);
        }

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👥 Пригласить друга', 'callback_data' => 'duel:invite'],
                        ],
                        [
                            ['text' => '🎲 Случайный соперник', 'callback_data' => 'duel:matchmaking'],
                        ],
                        [
                            ['text' => '📜 История дуэлей', 'callback_data' => 'duel:history'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendHelp($chatId): void
    {
        $text = implode("\n", [
            'ℹ️ <b>Подсказки</b>',
            '/story — сюжетные приключения по главам.',
            '/play — быстрые раунды по категориям.',
            '/duel — дуэль с друзьями (по нику @username или случайным соперником).',
            '/profile — твоя статистика.',
        ]);

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendUnknown($chatId): void
    {
        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => '🤔 Не понимаю эту команду. Попробуйте /help.',
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendProfile($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось загрузить профиль. Попробуйте ещё раз через /start.',
                ],
            ]);

            return;
        }

        try {
            $text = $this->profileFormatter->format($user);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось отформатировать профиль', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Профиль пока не доступен. Попробуй пройти раунд /play.',
                ],
            ]);

            return;
        }

        $text .= "\n\nПродолжай приключение — запусти /story или сыграй в /play!";

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
        ]);
    }

    private function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /**
     * @param array<string, mixed> $command
     */
    private function resolveUser(array $command): ?User
    {
        if (isset($command['user']) && $command['user'] instanceof User) {
            return $command['user'];
        }

        $from = $command['from'] ?? null;

        if (!is_array($from)) {
            return null;
        }

        try {
            return $this->userService->syncFromTelegram($from);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка синхронизации пользователя в команде', [
                'error' => $exception->getMessage(),
                'from' => $from,
            ]);

            return null;
        }
    }

    private function handleDuel($chatId, string $commandText, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось определить профиль. Попробуйте ещё раз с помощью /start.',
                ],
            ]);

            return;
        }

        $activeDuel = $this->duelService->findActiveDuelForUser($user);

        if ($activeDuel !== null && $activeDuel->status !== 'finished') {
            $this->sendDuelMenu($chatId, $activeDuel);

            return;
        }

        try {
            $newDuel = $this->duelService->createDuel($user);
            $this->sendDuelMenu($chatId, $newDuel);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось создать дуэль', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
                'command' => $commandText,
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Не получилось создать дуэль. Повтори попытку позже.',
                ],
            ]);
        }
    }

    private function formatDuelStatus(Duel $duel): string
    {
        $statusMap = [
            'waiting' => 'Ожидаем соперника.',
            'matched' => 'Соперник найден, скоро старт!',
            'in_progress' => 'Идёт сражение! Следи за вопросами.',
            'finished' => 'Дуэль завершена.',
            'cancelled' => 'Дуэль отменена.',
        ];

        $statusText = $statusMap[$duel->status] ?? ('Статус: ' . $duel->status);

        if ($duel->opponent_user_id === null) {
            $statusText .= ' Пригласи друга по нику или используй поиск случайного соперника.';
        }

        return $statusText;
    }

    public function handleDuelUsernameInvite($chatId, User $initiator, string $rawInput): bool
    {
        $pendingDuel = $this->duelService->findPendingInvitationForUser($initiator);

        if ($pendingDuel === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Сначала нажми «👥 Пригласить друга» в меню дуэли, затем отправь ник соперника.',
                ],
            ]);

            return true;
        }

        $username = ltrim(trim($rawInput), '@');

        if ($username === '') {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Укажи ник соперника в формате @username.',
                ],
            ]);

            return true;
        }

        if (!empty($initiator->username) && strcasecmp($username, $initiator->username) === 0) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Нельзя вызвать самого себя на дуэль. Укажи ник друга.',
                ],
            ]);

            return true;
        }

        $target = $this->userService->findByUsername($username);

        if (!$target instanceof User) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('Не нашёл игрока с ником <b>@%s</b>. Попроси друга написать /start боту.', htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                    'parse_mode' => 'HTML',
                ],
            ]);

            return true;
        }

        if ($target->telegram_id === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('Игрок @%s ещё не запустил бота. Попроси его отправить /start.', htmlspecialchars((string) $target->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                    'parse_mode' => 'HTML',
                ],
            ]);

            return true;
        }

        if ($this->duelService->findActiveDuelForUser($target) !== null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('%s сейчас участвует в другой дуэли. Попробуй позже.', $this->formatUserName($target)),
                    'parse_mode' => 'HTML',
                ],
            ]);

            return true;
        }

        $duel = $this->duelService->attachTarget($pendingDuel, $target);

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => sprintf('📨 Приглашение отправлено %s. Ждём подтверждение.', $this->formatUserName($target)),
                'parse_mode' => 'HTML',
            ],
        ]);

        $this->sendDuelInvitationToUser($target, $duel, $initiator);

        return true;
    }

    private function handleAdmin($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Ошибка: не удалось определить пользователя.',
                ],
            ]);

            return;
        }

        if (!$this->adminService->isAdmin($user)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ У вас нет прав администратора.',
                ],
            ]);

            return;
        }

        $this->sendAdminPanel($chatId);
    }

    private function sendAdminPanel($chatId): void
    {
        $text = "🔧 <b>Админ-панель</b>\n\nВыберите действие:";

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '⚔️ Завершить все активные дуэли',
                                'callback_data' => 'admin:finish_all_duels',
                            ],
                        ],
                        [
                            [
                                'text' => '📊 Статистика',
                                'callback_data' => 'admin:stats',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}

