<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates\Handlers;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use Symfony\Contracts\Cache\CacheInterface;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StoryService;
use QuizBot\Application\Services\AdminService;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Domain\Model\User;
use QuizBot\Presentation\Updates\Handlers\Concerns\SendsDuelMessages;

final class MessageHandler
{
    use SendsDuelMessages;
    private ClientInterface $telegramClient;

    private Logger $logger;

    private CacheInterface $cache;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private ProfileFormatter $profileFormatter;

    private StoryService $storyService;

    private AdminService $adminService;

    private TrueFalseService $trueFalseService;

    private StatisticsService $statisticsService;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        CacheInterface $cache,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter,
        AdminService $adminService,
        TrueFalseService $trueFalseService,
        StatisticsService $statisticsService
    ) {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->profileFormatter = $profileFormatter;
        $this->adminService = $adminService;
        $this->trueFalseService = $trueFalseService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * @param array<string, mixed> $message
     */
    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? null;
        $user = null;

        if ($chatId === null) {
            $this->logger->warning('Сообщение без chat_id', $message);

            return;
        }

        if (is_array($from)) {
            try {
                $user = $this->userService->syncFromTelegram($from);
            } catch (\Throwable $exception) {
                $this->logger->error('Не удалось синхронизировать пользователя', [
                    'error' => $exception->getMessage(),
                    'from' => $from,
                ]);
            }
        }

        if (isset($message['text']) && $this->startsWith($message['text'], '/')) {
            $commandHandler = new CommandHandler(
                $this->telegramClient,
                $this->logger,
                $this->userService,
                $this->duelService,
                $this->gameSessionService,
                $this->storyService,
                $this->profileFormatter,
                $this->adminService,
                $this->trueFalseService,
                $this->statisticsService,
                $this->cache
            );
            $commandHandler->handle([
                'chat_id' => $chatId,
                'command' => trim($message['text']),
                'from' => $from,
                'user' => $user,
            ]);

            return;
        }

        if (isset($message['text'])) {
            $text = trim($message['text']);
            
            $this->logger->debug('Обработка текстового сообщения', [
                'text' => $text,
                'text_length' => strlen($text),
                'chat_id' => $chatId,
            ]);
            
            // Обработка кнопок клавиатуры (проверяем первыми, до создания CommandHandler)
            if ($text === '⚔️ Дуэль' || $text === 'Дуэль') {
                $this->logger->debug('Обработка кнопки Дуэль');
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                $this->adminService,
                $this->trueFalseService,
                $this->statisticsService,
                $this->cache
            );
            $commandHandler->handle([
                'chat_id' => $chatId,
                'command' => '/duel',
                    'from' => $from,
                    'user' => $user,
                ]);
                return;
            }

            if ($text === '📊 Профиль' || $text === 'Профиль') {
                $this->logger->debug('Обработка кнопки Профиль');
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                $this->adminService,
                $this->trueFalseService,
                $this->statisticsService,
                $this->cache
            );
            $commandHandler->handle([
                'chat_id' => $chatId,
                'command' => '/profile',
                    'from' => $from,
                    'user' => $user,
                ]);
                return;
            }

            if ($text === '🏆 Рейтинг' || $text === 'Рейтинг') {
                $this->logger->debug('Обработка кнопки Рейтинг');
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => "🏆 <b>Выбери рейтинг</b>\n\nКакой рейтинг ты хочешь посмотреть?",
                        'parse_mode' => 'HTML',
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => '⚔️ Дуэли', 'callback_data' => 'rating:duel'],
                                ],
                                [
                                    ['text' => '🧠 Правда или ложь', 'callback_data' => 'rating:tf'],
                                ],
                            ],
                        ],
                    ],
                ]);
                return;
            }

            if ($text === '🧠 Правда или ложь' || $text === 'Правда или ложь') {
                $this->logger->debug('Обработка кнопки Правда или ложь');
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                $this->adminService,
                $this->trueFalseService,
                $this->statisticsService,
                $this->cache
            );
            $commandHandler->handle([
                'chat_id' => $chatId,
                'command' => '/truth',
                    'from' => $from,
                    'user' => $user,
                ]);
                return;
            }

            if ($text === '🆘 Тех.поддержка' || $text === 'Тех.поддержка' || $text === 'Техподдержка') {
                $this->logger->debug('Обработка кнопки Тех.поддержка');
                $this->handleSupportRequest($chatId, $user);
                return;
            }
            
            $this->logger->debug('Текст не соответствует кнопкам клавиатуры', [
                'text' => $text,
                'is_duel' => ($text === '⚔️ Дуэль' || $text === 'Дуэль'),
                'is_profile' => ($text === '📊 Профиль' || $text === 'Профиль'),
                'is_rating' => ($text === '🏆 Рейтинг' || $text === 'Рейтинг'),
            ]);

            // Проверяем, ожидает ли система сообщения от пользователя для тех.поддержки (ПЕРВЫМ, ДО создания CommandHandler!)
            $this->logger->debug('Перед проверкой флага тех.поддержки', [
                'user_is_instance' => ($user instanceof User),
                'user_id' => $user?->getKey(),
                'is_admin' => ($user instanceof User ? $this->adminService->isAdmin($user) : false),
            ]);
            
            if ($user instanceof User && !$this->adminService->isAdmin($user)) {
                $supportCacheKey = sprintf('user:support_message:%d', $user->getKey());
                $this->logger->debug('Проверка флага тех.поддержки', [
                    'cache_key' => $supportCacheKey,
                    'user_id' => $user->getKey(),
                    'text' => $text,
                ]);
                try {
                    $isSupportRequest = $this->cache->get($supportCacheKey, static function () {
                        return null;
                    });
                    
                    $this->logger->debug('Значение флага тех.поддержки', [
                        'cache_key' => $supportCacheKey,
                        'is_support_request' => $isSupportRequest,
                        'is_true' => ($isSupportRequest === true),
                        'is_strict_true' => ($isSupportRequest === true),
                        'type' => gettype($isSupportRequest),
                        'var_export' => var_export($isSupportRequest, true),
                    ]);
                    
                    $this->logger->debug('Проверка условия if', [
                        'isSupportRequest' => $isSupportRequest,
                        'isSupportRequest === true' => ($isSupportRequest === true),
                        'isSupportRequest == true' => ($isSupportRequest == true),
                    ]);
                    
                    if ($isSupportRequest === true) {
                        $this->logger->debug('Условие if выполнено, входим в блок обработки');
                        // Пользователь отправил сообщение в тех.поддержку
                        $this->logger->info('Обработка сообщения тех.поддержки', [
                            'user_id' => $user->getKey(),
                            'text' => $text,
                        ]);
                        $this->cache->delete($supportCacheKey);
                        $this->adminService->sendFeedbackToAdmins($user, $text);
                        $this->telegramClient->request('POST', 'sendMessage', [
                            'json' => [
                                'chat_id' => $chatId,
                                'text' => '✅ Ваше сообщение отправлено администраторам. Спасибо за обратную связь!',
                                'reply_markup' => $this->getMainKeyboard(),
                            ],
                        ]);
                        return;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Ошибка при проверке флага тех.поддержки', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'cache_key' => $supportCacheKey,
                    ]);
                }
            }

            // Обработка других текстовых сообщений
            $commandHandler = new CommandHandler(
                $this->telegramClient,
                $this->logger,
                $this->userService,
                $this->duelService,
                $this->gameSessionService,
                $this->storyService,
                $this->profileFormatter,
                $this->adminService,
                $this->trueFalseService,
                $this->statisticsService,
                $this->cache
            );

            // Если это админ и он ввёл @username — сначала пробуем завершить дуэль по нику
            if ($user instanceof User
                && $this->adminService->isAdmin($user)
                && $this->looksLikeUsernameInput($text)
            ) {
                $this->logger->debug('Админ ввёл юзернейм, пробуем завершить дуэль по нику', [
                    'username' => $text,
                    'user_id' => $user->getKey(),
                ]);

                $this->handleAdminFinishDuelByUsername($chatId, $user, $text);
                return;
            }

            // Проверяем, ожидает ли админ ввода ответа пользователю
            if ($user instanceof User && $this->adminService->isAdmin($user)) {
                $this->logger->debug('Проверка флага ответа админа', [
                    'admin_id' => $user->getKey(),
                    'text' => $text,
                ]);
                $cacheKeyPrefix = sprintf('admin:reply_to_user:%d:', $user->getKey());
                try {
                    // Ищем ключ в кеше (формат: admin:reply_to_user:{admin_id}:{target_user_id})
                    $found = false;
                    $targetUserId = null;
                    
                    // Пробуем найти ключ через перебор возможных ID (не идеально, но работает)
                    // В реальности лучше использовать более умный подход, но для простоты так
                    for ($i = 1; $i <= 10000; $i++) {
                        $testKey = $cacheKeyPrefix . $i;
                        try {
                            $value = $this->cache->get($testKey, static function () {
                                return null;
                            });
                            $this->logger->debug('Проверка ключа кеша для ответа админа', [
                                'test_key' => $testKey,
                                'value' => $value,
                                'is_true' => ($value === true),
                            ]);
                            if ($value === true) {
                                $found = true;
                                $targetUserId = $i;
                                $this->logger->info('Найден флаг ответа админа', [
                                    'cache_key' => $testKey,
                                    'target_user_id' => $targetUserId,
                                ]);
                                break;
                            }
                        } catch (\Throwable $e) {
                            $this->logger->debug('Ошибка при проверке ключа кеша', [
                                'test_key' => $testKey,
                                'error' => $e->getMessage(),
                            ]);
                            // Продолжаем поиск
                        }
                    }
                    
                    if ($found && $targetUserId !== null) {
                        $this->cache->delete($cacheKeyPrefix . $targetUserId);
                        $this->logger->info('Отправка ответа админа пользователю', [
                            'admin_id' => $user->getKey(),
                            'target_user_id' => $targetUserId,
                            'text' => $text,
                        ]);
                        $this->sendAdminReplyToUser($chatId, $user, $targetUserId, $text);
                        return;
                    } else {
                        $this->logger->debug('Флаг ответа админа не найден', [
                            'admin_id' => $user->getKey(),
                            'searched_keys' => '1-10000',
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Ошибка при поиске флага ответа админа', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Обычная обработка юзернейма для приглашения в дуэль
            if ($user instanceof User && $this->looksLikeUsernameInput($text)) {
                if ($commandHandler->handleDuelUsernameInvite($chatId, $user, $text)) {
                    return;
                }
            }
        }

        // Если сообщение не обработано - показываем приветствие
        $this->sendWelcome($chatId);
    }

    /**
     * @param int|string $chatId
     */
    private function sendWelcome($chatId): void
    {
        $text = implode("\n", [
            '👋 Привет! Это викторина «Битва знаний».',
            'Доступны дуэли, мини-игры и подробный профиль.',
            'Команды: /duel, /profile, /truth, /help.',
        ]);

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getMainKeyboard(),
            ],
        ]);
    }
    
    /**
     * Возвращает клавиатуру с основными кнопками меню
     */
    private function getMainKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '⚔️ Дуэль'],
                ],
                [
                    ['text' => '📊 Профиль'],
                    ['text' => '🏆 Рейтинг'],
                ],
                [
                    ['text' => '🆘 Тех.поддержка'],
                ],
                [
                    ['text' => '🧠 Правда или ложь'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Обрабатывает запрос на тех.поддержку
     */
    private function handleSupportRequest($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Не удалось определить пользователя. Попробуйте /start.',
                ],
            ]);
            return;
        }

        // Устанавливаем флаг ожидания сообщения от пользователя
        // Используем базу данных для хранения флага, так как ArrayAdapter не сохраняет данные между запросами
        $supportCacheKey = sprintf('user:support_message:%d', $user->getKey());
        try {
            // Удаляем старый флаг
            $this->cache->delete($supportCacheKey);
            // Устанавливаем флаг с TTL 1 час (3600 секунд)
            // Используем get с callback, который вернет true и сохранит это значение
            $result = $this->cache->get($supportCacheKey, static function () {
                return true;
            }, 3600);
            $this->logger->debug('Флаг тех.поддержки установлен', [
                'cache_key' => $supportCacheKey,
                'user_id' => $user->getKey(),
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при установке флага тех.поддержки', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'cache_key' => $supportCacheKey,
            ]);
        }

        $text = "🆘 <b>Техническая поддержка</b>\n\n" .
                "Опишите вашу проблему или вопрос, и мы обязательно поможем!\n\n" .
                "Напишите сообщение, и оно будет отправлено администраторам.";

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getMainKeyboard(),
            ],
        ]);
    }

    private function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    private function looksLikeUsernameInput(string $text): bool
    {
        return (bool) preg_match('/^@[A-Za-z0-9_]{5,}$/', $text);
    }

    /**
     * Отправляет ответ админа пользователю
     */
    private function sendAdminReplyToUser($adminChatId, User $adminUser, int $targetUserId, string $replyText): void
    {
        $targetUser = User::find($targetUserId);
        if (!$targetUser instanceof User || $targetUser->telegram_id === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $adminChatId,
                    'text' => '❌ Не удалось найти пользователя для ответа или у него нет Telegram ID.',
                ],
            ]);
            $this->logger->warning('Не удалось найти пользователя для ответа админа', ['target_user_id' => $targetUserId]);
            return;
        }

        $adminName = $this->formatUserName($adminUser);
        $messageToUser = sprintf(
            "📩 <b>Ответ от администратора</b>\n\n" .
            "От: %s\n" .
            "Сообщение:\n<i>%s</i>",
            $adminName,
            htmlspecialchars($replyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        try {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $targetUser->telegram_id,
                    'text' => $messageToUser,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $adminChatId,
                    'text' => sprintf('✅ Ответ отправлен пользователю %s.', $this->formatUserName($targetUser)),
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
            $this->logger->info('Админ ответил пользователю', [
                'admin_id' => $adminUser->getKey(),
                'target_user_id' => $targetUserId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при отправке ответа пользователю от админа', [
                'admin_id' => $adminUser->getKey(),
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $adminChatId,
                    'text' => '❌ Ошибка при отправке ответа: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ],
            ]);
        }
    }

    private function formatUserName(User $user): string
    {
        if (!empty($user->first_name) && !empty($user->last_name)) {
            return htmlspecialchars($user->first_name . ' ' . $user->last_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->first_name)) {
            return htmlspecialchars($user->first_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->username)) {
            return '@' . htmlspecialchars($user->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return 'Пользователь #' . $user->getKey();
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

    protected function getMessageFormatter(): ?\QuizBot\Application\Services\MessageFormatter
    {
        // MessageHandler не имеет MessageFormatter, возвращаем null
        return null;
    }

    private function handleAdminFinishDuelByUsername($chatId, User $admin, string $usernameInput): void
    {
        if (!$this->adminService->isAdmin($admin)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ У вас нет прав администратора.',
                ],
            ]);
            return;
        }

        $username = ltrim(trim($usernameInput), '@');
        $targetUser = $this->userService->findByUsername($username);

        if ($targetUser === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('❌ Не найден игрок с ником <b>@%s</b>.', htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                    'parse_mode' => 'HTML',
                ],
            ]);
            return;
        }

        // Ищем активную дуэль этого игрока
        $activeDuel = $this->duelService->findActiveDuelForUser($targetUser);

        if ($activeDuel === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('❌ У игрока <b>@%s</b> нет активных дуэлей.', htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                    'parse_mode' => 'HTML',
                ],
            ]);
            return;
        }

        try {
            // Завершаем дуэль (это автоматически отправит результаты обоим игрокам)
            $result = $this->duelService->finalizeDuel($activeDuel);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf(
                        "✅ <b>Дуэль завершена</b>\n\n" .
                        "Дуэль <b>%s</b> успешно завершена.\n" .
                        "Результаты отправлены обоим игрокам.",
                        htmlspecialchars($activeDuel->code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    ),
                    'parse_mode' => 'HTML',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при завершении дуэли по нику', [
                'error' => $e->getMessage(),
                'username' => $username,
                'duel_id' => $activeDuel->getKey(),
                'exception' => $e,
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => sprintf('❌ Ошибка при завершении дуэли: %s', htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                ],
            ]);
        }
    }
}

