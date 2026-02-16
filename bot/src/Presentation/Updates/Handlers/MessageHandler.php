<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates\Handlers;

use GuzzleHttp\ClientInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
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
use QuizBot\Application\Services\ReferralService;
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

    private ReferralService $referralService;

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
        StatisticsService $statisticsService,
        ReferralService $referralService
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
        $this->referralService = $referralService;
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
                $this->cache,
                $this->referralService
            );
            $commandHandler->handle([
                'chat_id' => $chatId,
                'command' => trim($message['text']),
                'from' => $from,
                'user' => $user,
            ]);

            return;
        }

        if (isset($message['document']) && is_array($message['document'])) {
            if (!($user instanceof User) || !$this->adminService->isAdmin($user)) {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '⛔️ Загрузка вопросов доступна только администраторам.',
                    ],
                ]);
                return;
            }

            $caption = isset($message['caption']) && is_string($message['caption']) ? trim($message['caption']) : '';
            if ($this->shouldImportFactsFromDocument($user, $caption)) {
                $this->handleAdminFactsFileImport((int) $chatId, $user, $message['document']);
                $this->disableFactsImportMode($user);
                return;
            }

            $this->handleAdminQuestionsFileImport((int) $chatId, $user, $message['document'], $caption);
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
                $this->cache,
                $this->referralService
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
                $this->cache,
                $this->referralService
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
                $this->cache,
                $this->referralService
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
                $this->cache,
                $this->referralService
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

                // Проверяем, ожидает ли админ ввода вопроса
                $addQuestionKey = sprintf('admin:adding_question:%d', $user->getKey());
                try {
                    $categoryId = $this->cache->get($addQuestionKey, static function () {
                        return null;
                    });
                    
                    if ($categoryId !== null && is_numeric($categoryId)) {
                        $this->handleAdminAddQuestion($chatId, $user, (int) $categoryId, $text);
                        return;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Ошибка при проверке флага добавления вопроса', [
                        'error' => $e->getMessage(),
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
            'Я обрабатываю только команды из /help.',
            'Нажмите кнопку 🎮 Играть, чтобы открыть приложение.',
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
        $webappUrl = getenv('WEBAPP_URL');
        if (empty($webappUrl)) {
            return ['remove_keyboard' => true];
        }

        return [
            'inline_keyboard' => [
                [
                    ['text' => '🎮 Играть', 'web_app' => ['url' => $webappUrl]],
                ],
            ],
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

    private function shouldImportFactsFromDocument(User $user, string $caption): bool
    {
        if ($caption !== '' && $this->startsWith(mb_strtolower($caption), '/import_facts')) {
            return true;
        }

        return $this->isFactsImportModeEnabled($user);
    }

    private function isFactsImportModeEnabled(User $user): bool
    {
        $cacheKey = sprintf('admin:import_facts_mode:%d', $user->getKey());
        try {
            $value = $this->cache->get($cacheKey, static function () {
                return null;
            });
            return $value === true;
        } catch (\Throwable $e) {
            $this->logger->warning('Ошибка чтения режима импорта фактов', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function disableFactsImportMode(User $user): void
    {
        $cacheKey = sprintf('admin:import_facts_mode:%d', $user->getKey());
        try {
            $this->cache->delete($cacheKey);
        } catch (\Throwable $e) {
            $this->logger->debug('Не удалось отключить режим импорта фактов', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
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

    /**
     * @param array<string, mixed> $document
     */
    private function handleAdminQuestionsFileImport(int $chatId, User $admin, array $document, string $caption): void
    {
        $fileName = isset($document['file_name']) ? (string) $document['file_name'] : 'questions.txt';
        $mimeType = isset($document['mime_type']) ? mb_strtolower((string) $document['mime_type']) : '';
        $ext = mb_strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = isset($document['file_size']) ? (int) $document['file_size'] : 0;
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        $allowedExtensions = ['txt', 'json', 'ndjson', 'jsonl'];
        $allowedMimeTypes = ['text/plain', 'application/json', 'application/x-ndjson'];

        if (!in_array($ext, $allowedExtensions, true) && !in_array($mimeType, $allowedMimeTypes, true)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Нужен текстовый файл вопросов (.txt, .json, .ndjson).',
                ],
            ]);
            return;
        }

        if ($fileSize > $maxFileSize) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Файл слишком большой. Максимум: 2MB.',
                ],
            ]);
            return;
        }

        try {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⏳ Импортирую вопросы из файла...',
                ],
            ]);

            $content = $this->downloadTelegramDocument($document);
            $defaultCategory = $this->extractCategoryFromCaption($caption);
            $result = $this->importQuestionsFromFileContent($content, $fileName, $defaultCategory);

            $summary = sprintf(
                "✅ Импорт завершен.\n\nФайл: %s\nДобавлено: %d\nДублей пропущено: %d\nОшибок: %d",
                htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $result['inserted'],
                $result['skipped_duplicates'] ?? 0,
                count($result['errors'])
            );

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $summary .= "\n\nПервые ошибки:\n- " . implode("\n- ", array_map(static function (string $e): string {
                    return htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }, $preview));
            }

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $summary,
                    'parse_mode' => 'HTML',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка импорта файла вопросов админом', [
                'admin_id' => $admin->getKey(),
                'telegram_id' => $admin->telegram_id,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "❌ Не удалось импортировать файл.\n\nФормат:\nCATEGORY: География\nQ: Вопрос?\n+ Правильный ответ\n- Неправильный ответ\n- Неправильный ответ\n\n(пустая строка между вопросами)",
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $document
     */
    private function handleAdminFactsFileImport(int $chatId, User $admin, array $document): void
    {
        $fileName = isset($document['file_name']) ? (string) $document['file_name'] : 'facts.txt';
        $mimeType = isset($document['mime_type']) ? mb_strtolower((string) $document['mime_type']) : '';
        $ext = mb_strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = isset($document['file_size']) ? (int) $document['file_size'] : 0;
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        $allowedExtensions = ['txt', 'json', 'ndjson', 'jsonl'];
        $allowedMimeTypes = ['text/plain', 'application/json', 'application/x-ndjson'];

        if (!in_array($ext, $allowedExtensions, true) && !in_array($mimeType, $allowedMimeTypes, true)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Нужен текстовый файл фактов (.txt, .json, .ndjson).',
                ],
            ]);
            return;
        }

        if ($fileSize > $maxFileSize) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Файл слишком большой. Максимум: 2MB.',
                ],
            ]);
            return;
        }

        try {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⏳ Импортирую факты из файла...',
                ],
            ]);

            $content = $this->downloadTelegramDocument($document);
            $result = $this->importFactsFromFileContent($content, $fileName);

            $summary = sprintf(
                "✅ Импорт фактов завершен.\n\nФайл: %s\nДобавлено: %d\nДублей пропущено: %d\nОшибок: %d",
                htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $result['inserted'],
                $result['skipped_duplicates'] ?? 0,
                count($result['errors'])
            );

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $summary .= "\n\nПервые ошибки:\n- " . implode("\n- ", array_map(static function (string $e): string {
                    return htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }, $preview));
            }

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $summary,
                    'parse_mode' => 'HTML',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка импорта фактов админом', [
                'admin_id' => $admin->getKey(),
                'telegram_id' => $admin->telegram_id,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "❌ Не удалось импортировать факты.\n\nФормат TXT:\nУтверждение\ntrue|false\nПояснение (опционально)",
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $document
     */
    private function downloadTelegramDocument(array $document): string
    {
        $fileId = isset($document['file_id']) ? (string) $document['file_id'] : '';
        if ($fileId === '') {
            throw new \RuntimeException('В документе отсутствует file_id');
        }

        $baseUri = (string) $this->telegramClient->getConfig('base_uri');
        if ($baseUri === '') {
            throw new \RuntimeException('Не удалось определить base_uri Telegram клиента');
        }

        if (preg_match('#/bot([^/]+)/?$#', $baseUri, $matches) !== 1) {
            throw new \RuntimeException('Не удалось извлечь токен бота из base_uri');
        }
        $botToken = $matches[1];

        $response = $this->telegramClient->request('POST', 'getFile', [
            'json' => ['file_id' => $fileId],
        ]);
        $payload = json_decode((string) $response->getBody(), true);

        $filePath = isset($payload['result']['file_path']) ? (string) $payload['result']['file_path'] : '';
        if ($filePath === '') {
            throw new \RuntimeException('Telegram не вернул file_path');
        }

        $fileResponse = $this->telegramClient->request('GET', sprintf(
            'https://api.telegram.org/file/bot%s/%s',
            $botToken,
            $filePath
        ));

        $content = (string) $fileResponse->getBody();
        if (trim($content) === '') {
            throw new \RuntimeException('Файл пустой');
        }

        return $content;
    }

    private function extractCategoryFromCaption(string $caption): ?string
    {
        if ($caption === '') {
            return null;
        }

        if (preg_match('/category\\s*:\\s*(.+)$/i', $caption, $m) === 1) {
            $value = trim($m[1]);
            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importQuestionsFromFileContent(string $content, string $fileName, ?string $defaultCategory): array
    {
        $lowerFileName = mb_strtolower($fileName);
        $isNdjson = $this->endsWith($lowerFileName, '.ndjson') || $this->endsWith($lowerFileName, '.jsonl');
        $isJson = $this->endsWith($lowerFileName, '.json');

        if ($isNdjson || $isJson) {
            return $this->importQuestionsFromJsonContent($content, $isNdjson);
        }

        return $this->importQuestionsFromPlainText($content, $defaultCategory);
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importFactsFromFileContent(string $content, string $fileName): array
    {
        $lowerFileName = mb_strtolower($fileName);
        $isNdjson = $this->endsWith($lowerFileName, '.ndjson') || $this->endsWith($lowerFileName, '.jsonl');
        $isJson = $this->endsWith($lowerFileName, '.json');

        if ($isNdjson || $isJson) {
            return $this->importFactsFromJsonContent($content, $isNdjson);
        }

        return $this->importFactsFromPlainText($content);
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importFactsFromJsonContent(string $content, bool $isNdjson): array
    {
        $records = [];

        if ($isNdjson) {
            $lines = preg_split('/\\R/', $content) ?: [];
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException(sprintf('Некорректный JSON в строке %d', $index + 1));
                }
                $records[] = $decoded;
            }
        } else {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('JSON файл должен содержать массив фактов');
            }
            $records = isset($decoded['facts']) && is_array($decoded['facts']) ? $decoded['facts'] : $decoded;
        }

        $inserted = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $hasContentHash = Capsule::schema()->hasColumn('true_false_facts', 'content_hash');
        $seenHashes = [];

        Capsule::connection()->transaction(function () use ($records, &$inserted, &$skippedDuplicates, &$errors, $hasContentHash, &$seenHashes): void {
            $now = date('Y-m-d H:i:s');

            foreach ($records as $idx => $record) {
                try {
                    if (!is_array($record)) {
                        throw new \RuntimeException('Элемент факта не объект');
                    }

                    $statement = trim((string) ($record['statement'] ?? $record['fact'] ?? ''));
                    if ($statement === '') {
                        throw new \RuntimeException('Пустое утверждение');
                    }
                    $contentHash = $this->buildFactContentHash($statement);
                    if ($hasContentHash) {
                        if (isset($seenHashes[$contentHash]) || Capsule::table('true_false_facts')->where('content_hash', $contentHash)->exists()) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    $truthRaw = $record['is_true'] ?? $record['truth'] ?? $record['answer'] ?? null;
                    $isTrue = $this->normalizeTruthValue($truthRaw);
                    if ($isTrue === null) {
                        throw new \RuntimeException('Поле is_true/answer не распознано');
                    }

                    $explanation = isset($record['explanation']) ? trim((string) $record['explanation']) : '';

                    $row = [
                        'statement' => $statement,
                        'explanation' => $explanation !== '' ? $explanation : null,
                        'is_true' => $isTrue,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasContentHash) {
                        $row['content_hash'] = $contentHash;
                    }

                    Capsule::table('true_false_facts')->insert($row);
                    if ($hasContentHash) {
                        $seenHashes[$contentHash] = true;
                    }
                    $inserted++;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('JSON #%d: %s', $idx + 1, $e->getMessage());
                }
            }
        });

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importFactsFromPlainText(string $content): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split('/\\n\\s*\\n/', $normalized) ?: [];

        $inserted = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $hasContentHash = Capsule::schema()->hasColumn('true_false_facts', 'content_hash');
        $seenHashes = [];

        Capsule::connection()->transaction(function () use ($blocks, &$inserted, &$skippedDuplicates, &$errors, $hasContentHash, &$seenHashes): void {
            $now = date('Y-m-d H:i:s');

            foreach ($blocks as $blockIndex => $blockRaw) {
                $block = trim($blockRaw);
                if ($block === '') {
                    continue;
                }

                try {
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static function (string $line): bool {
                        return $line !== '' && strpos($line, '#') !== 0;
                    }));

                    if (count($lines) < 2) {
                        throw new \RuntimeException('Нужно минимум 2 строки: утверждение и true/false');
                    }

                    $statement = preg_replace('/^(fact|statement|s)\\s*:\\s*/i', '', $lines[0]) ?? $lines[0];
                    $statement = trim($statement);
                    if ($statement === '') {
                        throw new \RuntimeException('Пустое утверждение');
                    }
                    $contentHash = $this->buildFactContentHash($statement);
                    if ($hasContentHash) {
                        if (isset($seenHashes[$contentHash]) || Capsule::table('true_false_facts')->where('content_hash', $contentHash)->exists()) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    $isTrue = $this->normalizeTruthValue($lines[1]);
                    if ($isTrue === null) {
                        throw new \RuntimeException('Вторая строка должна быть true/false (или правда/ложь, 1/0)');
                    }

                    $explanationLines = array_slice($lines, 2);
                    $explanation = trim(implode("\n", $explanationLines));

                    $row = [
                        'statement' => $statement,
                        'explanation' => $explanation !== '' ? $explanation : null,
                        'is_true' => $isTrue,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasContentHash) {
                        $row['content_hash'] = $contentHash;
                    }

                    Capsule::table('true_false_facts')->insert($row);
                    if ($hasContentHash) {
                        $seenHashes[$contentHash] = true;
                    }
                    $inserted++;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Блок #%d: %s', $blockIndex + 1, $e->getMessage());
                }
            }
        });

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
        ];
    }

    /**
     * @param mixed $value
     */
    private function normalizeTruthValue($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return true;
            }
            if ((int) $value === 0) {
                return false;
            }
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $trueValues = ['1', 'true', 't', 'yes', 'y', 'правда', '+'];
        $falseValues = ['0', 'false', 'f', 'no', 'n', 'ложь', '-'];

        if (in_array($normalized, $trueValues, true)) {
            return true;
        }
        if (in_array($normalized, $falseValues, true)) {
            return false;
        }

        return null;
    }

    private function normalizeImportText(string $value): string
    {
        $value = str_replace('ё', 'е', mb_strtolower(trim($value)));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[!?.,;:…]+$/u', '', $value) ?? $value;

        return trim($value);
    }

    private function buildFactContentHash(string $statement): string
    {
        return sha1($this->normalizeImportText($statement));
    }

    private function buildQuestionContentHash(string $questionText, string $correctAnswerText): string
    {
        return sha1(
            $this->normalizeImportText($questionText)
            . '|'
            . $this->normalizeImportText($correctAnswerText)
        );
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importQuestionsFromJsonContent(string $content, bool $isNdjson): array
    {
        $records = [];

        if ($isNdjson) {
            $lines = preg_split('/\\R/', $content) ?: [];
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException(sprintf('Некорректный JSON в строке %d', $index + 1));
                }
                $records[] = $decoded;
            }
        } else {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('JSON файл должен содержать массив вопросов');
            }
            $records = isset($decoded['questions']) && is_array($decoded['questions']) ? $decoded['questions'] : $decoded;
        }

        $inserted = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $hasContentHash = Capsule::schema()->hasColumn('questions', 'content_hash');
        $seenHashes = [];

        Capsule::connection()->transaction(function () use ($records, &$inserted, &$skippedDuplicates, &$errors, $hasContentHash, &$seenHashes): void {
            $now = date('Y-m-d H:i:s');

            foreach ($records as $idx => $record) {
                try {
                    if (!is_array($record)) {
                        throw new \RuntimeException('Элемент вопроса не объект');
                    }

                    $categoryTitle = trim((string) ($record['category'] ?? ''));
                    if ($categoryTitle === '') {
                        throw new \RuntimeException('Не указана category');
                    }

                    $category = \QuizBot\Domain\Model\Category::query()
                        ->where('title', $categoryTitle)
                        ->first();
                    if (!$category) {
                        throw new \RuntimeException('Категория не найдена: ' . $categoryTitle);
                    }

                    $questionText = trim((string) ($record['question'] ?? $record['question_text'] ?? ''));
                    if ($questionText === '') {
                        throw new \RuntimeException('Пустой вопрос');
                    }

                    $answers = $record['answers'] ?? null;
                    if (!is_array($answers) || count($answers) < 2) {
                        throw new \RuntimeException('Нужно минимум 2 ответа');
                    }

                    $correctCount = 0;
                    $correctAnswerText = '';
                    $answerRows = [];
                    foreach ($answers as $a) {
                        if (!is_array($a)) {
                            continue;
                        }
                        $text = trim((string) ($a['text'] ?? $a['answer_text'] ?? ''));
                        if ($text === '') {
                            continue;
                        }
                        $isCorrect = (bool) ($a['is_correct'] ?? false);
                        if ($isCorrect) {
                            $correctCount++;
                            $correctAnswerText = $text;
                        }
                        $answerRows[] = [
                            'question_id' => 0,
                            'answer_text' => $text,
                            'is_correct' => $isCorrect,
                            'score_delta' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($correctCount !== 1 || count($answerRows) < 2) {
                        throw new \RuntimeException('В вопросе должен быть ровно 1 правильный ответ');
                    }

                    $contentHash = $this->buildQuestionContentHash($questionText, $correctAnswerText);
                    if ($hasContentHash) {
                        if (isset($seenHashes[$contentHash]) || Capsule::table('questions')->where('content_hash', $contentHash)->exists()) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    $questionRow = [
                        'category_id' => $category->getKey(),
                        'type' => 'multiple_choice',
                        'question_text' => $questionText,
                        'image_url' => isset($record['image_url']) ? (string) $record['image_url'] : null,
                        'explanation' => isset($record['explanation']) ? (string) $record['explanation'] : null,
                        'difficulty' => is_numeric($record['difficulty'] ?? null) ? (int) $record['difficulty'] : 1,
                        'time_limit' => is_numeric($record['time_limit'] ?? null) ? (int) $record['time_limit'] : 30,
                        'is_active' => true,
                        'tags' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasContentHash) {
                        $questionRow['content_hash'] = $contentHash;
                    }
                    $questionId = (int) Capsule::table('questions')->insertGetId($questionRow);

                    foreach ($answerRows as &$answerRow) {
                        $answerRow['question_id'] = $questionId;
                    }
                    unset($answerRow);

                    Capsule::table('answers')->insert($answerRows);
                    if ($hasContentHash) {
                        $seenHashes[$contentHash] = true;
                    }
                    $inserted++;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('JSON #%d: %s', $idx + 1, $e->getMessage());
                }
            }
        });

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{inserted:int,skipped_duplicates:int,errors:array<int,string>}
     */
    private function importQuestionsFromPlainText(string $content, ?string $defaultCategory): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split('/\\n\\s*\\n/', $normalized) ?: [];

        $inserted = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $globalCategory = $defaultCategory;
        $hasContentHash = Capsule::schema()->hasColumn('questions', 'content_hash');
        $seenHashes = [];

        Capsule::connection()->transaction(function () use ($blocks, &$inserted, &$skippedDuplicates, &$errors, &$globalCategory, $hasContentHash, &$seenHashes): void {
            $now = date('Y-m-d H:i:s');

            foreach ($blocks as $blockIndex => $blockRaw) {
                $block = trim($blockRaw);
                if ($block === '') {
                    continue;
                }

                try {
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static function (string $line): bool {
                        return $line !== '' && strpos($line, '#') !== 0;
                    }));

                    if (count($lines) === 0) {
                        continue;
                    }

                    $categoryTitle = $globalCategory;
                    if (preg_match('/^category\\s*:\\s*(.+)$/i', $lines[0], $m) === 1) {
                        $categoryTitle = trim($m[1]);
                        $globalCategory = $categoryTitle;
                        array_shift($lines);
                    }

                    if (!$categoryTitle) {
                        throw new \RuntimeException('Не указана категория. Добавьте CATEGORY: Название');
                    }

                    if (count($lines) < 3) {
                        throw new \RuntimeException('Слишком мало строк: нужен вопрос и минимум 2 ответа');
                    }

                    $questionLine = array_shift($lines);
                    $questionText = preg_replace('/^q\\s*:\\s*/i', '', $questionLine) ?? $questionLine;
                    $questionText = trim($questionText);
                    if ($questionText === '') {
                        throw new \RuntimeException('Пустой текст вопроса');
                    }

                    $category = \QuizBot\Domain\Model\Category::query()
                        ->where('title', $categoryTitle)
                        ->first();
                    if (!$category) {
                        throw new \RuntimeException('Категория не найдена: ' . $categoryTitle);
                    }

                    $answerRows = [];
                    $correctCount = 0;
                    $correctAnswerText = '';
                    foreach ($lines as $answerIndex => $line) {
                        $isCorrect = false;
                        $text = $line;

                        if (preg_match('/^\\+\\s*(.+)$/u', $line, $m) === 1) {
                            $isCorrect = true;
                            $text = trim($m[1]);
                        } elseif (preg_match('/^-\\s*(.+)$/u', $line, $m) === 1) {
                            $isCorrect = false;
                            $text = trim($m[1]);
                        } elseif (preg_match('/^a\\*\\s*:\\s*(.+)$/iu', $line, $m) === 1) {
                            $isCorrect = true;
                            $text = trim($m[1]);
                        } elseif (preg_match('/^a\\s*:\\s*(.+)$/iu', $line, $m) === 1) {
                            $isCorrect = false;
                            $text = trim($m[1]);
                        } else {
                            // Фолбэк: первый ответ считаем правильным
                            $isCorrect = $answerIndex === 0;
                            $text = trim($line);
                        }

                        if ($text === '') {
                            continue;
                        }
                        if ($isCorrect) {
                            $correctCount++;
                            $correctAnswerText = $text;
                        }

                        $answerRows[] = [
                            'question_id' => 0,
                            'answer_text' => $text,
                            'is_correct' => $isCorrect,
                            'score_delta' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($correctCount !== 1 || count($answerRows) < 2) {
                        throw new \RuntimeException('Нужен минимум 2 ответа и ровно 1 правильный');
                    }

                    $contentHash = $this->buildQuestionContentHash($questionText, $correctAnswerText);
                    if ($hasContentHash) {
                        if (isset($seenHashes[$contentHash]) || Capsule::table('questions')->where('content_hash', $contentHash)->exists()) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    $questionRow = [
                        'category_id' => $category->getKey(),
                        'type' => 'multiple_choice',
                        'question_text' => $questionText,
                        'image_url' => null,
                        'explanation' => null,
                        'difficulty' => 1,
                        'time_limit' => 30,
                        'is_active' => true,
                        'tags' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasContentHash) {
                        $questionRow['content_hash'] = $contentHash;
                    }
                    $questionId = (int) Capsule::table('questions')->insertGetId($questionRow);

                    foreach ($answerRows as &$answerRow) {
                        $answerRow['question_id'] = $questionId;
                    }
                    unset($answerRow);

                    Capsule::table('answers')->insert($answerRows);
                    if ($hasContentHash) {
                        $seenHashes[$contentHash] = true;
                    }
                    $inserted++;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Блок #%d: %s', $blockIndex + 1, $e->getMessage());
                }
            }
        });

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
        ];
    }

    private function endsWith(string $value, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        if (strlen($value) < strlen($suffix)) {
            return false;
        }

        return substr($value, -strlen($suffix)) === $suffix;
    }

    /**
     * Обрабатывает добавление вопроса от админа
     */
    private function handleAdminAddQuestion($chatId, User $admin, int $categoryId, string $input): void
    {
        // Проверяем команду отмены
        if (strtolower(trim($input)) === '/cancel') {
            $cacheKey = sprintf('admin:adding_question:%d', $admin->getKey());
            try {
                $this->cache->delete($cacheKey);
            } catch (\Throwable $e) {
                // Игнорируем
            }
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Добавление вопроса отменено.',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
            return;
        }

        // Парсим ввод: вопрос + 4 ответа (первый - правильный)
        $lines = array_filter(array_map('trim', explode("\n", $input)), fn($line) => $line !== '');

        if (count($lines) < 3) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "❌ <b>Неверный формат!</b>\n\nОтправьте вопрос в формате:\n\n<code>Текст вопроса?\nПравильный ответ\nНеправильный ответ 1\nНеправильный ответ 2\nНеправильный ответ 3</code>\n\nМинимум: вопрос + 2 ответа.\nОтправьте /cancel для отмены.",
                    'parse_mode' => 'HTML',
                ],
            ]);
            return;
        }

        $questionText = array_shift($lines);
        $correctAnswer = array_shift($lines);
        $incorrectAnswers = array_slice($lines, 0, 3); // Максимум 3 неправильных ответа

        try {
            $category = \QuizBot\Domain\Model\Category::find($categoryId);
            if (!$category) {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '❌ Категория не найдена.',
                    ],
                ]);
                return;
            }

            // Создаём вопрос
            $question = new \QuizBot\Domain\Model\Question();
            $question->category_id = $categoryId;
            $question->question_text = $questionText;
            $question->difficulty = 2; // medium
            $question->is_active = true;
            $question->save();

            // Создаём правильный ответ
            $answer = new \QuizBot\Domain\Model\Answer();
            $answer->question_id = $question->getKey();
            $answer->answer_text = $correctAnswer;
            $answer->is_correct = true;
            $answer->save();

            // Создаём неправильные ответы
            foreach ($incorrectAnswers as $wrongAnswer) {
                $answer = new \QuizBot\Domain\Model\Answer();
                $answer->question_id = $question->getKey();
                $answer->answer_text = $wrongAnswer;
                $answer->is_correct = false;
                $answer->save();
            }

            // Удаляем флаг из кеша
            $cacheKey = sprintf('admin:adding_question:%d', $admin->getKey());
            try {
                $this->cache->delete($cacheKey);
            } catch (\Throwable $e) {
                // Игнорируем
            }

            $totalQuestions = \QuizBot\Domain\Model\Question::query()
                ->where('category_id', $categoryId)
                ->count();

            $text = sprintf(
                "✅ <b>Вопрос добавлен!</b>\n\n" .
                "📚 Категория: %s %s\n" .
                "❓ Вопрос: %s\n" .
                "✓ Правильный ответ: %s\n" .
                "✗ Неправильных ответов: %d\n\n" .
                "Всего вопросов в категории: %d",
                $category->icon ?? '📚',
                htmlspecialchars($category->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($questionText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($correctAnswer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                count($incorrectAnswers),
                $totalQuestions
            );

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '➕ Добавить ещё вопрос',
                                    'callback_data' => 'admin:add_q_cat:' . $categoryId,
                                ],
                            ],
                            [
                                [
                                    'text' => '📋 Выбрать другую категорию',
                                    'callback_data' => 'admin:add_question',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $this->logger->info('Админ добавил вопрос', [
                'admin_id' => $admin->getKey(),
                'category_id' => $categoryId,
                'question_id' => $question->getKey(),
                'question_text' => $questionText,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при добавлении вопроса', [
                'error' => $e->getMessage(),
                'admin_id' => $admin->getKey(),
                'category_id' => $categoryId,
            ]);
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '❌ Ошибка при добавлении вопроса: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ],
            ]);
        }
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
