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
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Application\Services\ReferralService;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\TrueFalseFact;
use QuizBot\Presentation\Updates\Handlers\Concerns\SendsDuelMessages;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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

    private TrueFalseService $trueFalseService;

    private StatisticsService $statisticsService;

    private CacheInterface $cache;

    private ReferralService $referralService;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter,
        AdminService $adminService,
        TrueFalseService $trueFalseService,
        StatisticsService $statisticsService,
        CacheInterface $cache,
        ReferralService $referralService
    ) {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->profileFormatter = $profileFormatter;
        $this->adminService = $adminService;
        $this->trueFalseService = $trueFalseService;
        $this->statisticsService = $statisticsService;
        $this->cache = $cache;
        $this->referralService = $referralService;
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

        $this->logger->debug('CommandHandler::handle вызван', [
            'chat_id' => $chatId,
            'command' => $commandText,
        ]);

        if ($chatId === null || $commandText === null) {
            $this->logger->warning('Некорректная команда', $command);

            return;
        }

        $user = $this->resolveUser($command);

        $normalized = strtolower($commandText);
        
        $this->logger->debug('Обработка команды', [
            'normalized' => $normalized,
            'user_id' => $user?->getKey(),
        ]);

        if ($this->startsWith($normalized, '/start')) {
            $this->sendStart($chatId, $user, $commandText);

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

        if ($this->startsWith($normalized, '/leaderboard') || $this->startsWith($normalized, '/rating') || $this->startsWith($normalized, '/top')) {
            $this->logger->debug('Вызов sendLeaderboard');
            $this->sendLeaderboard($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/truth') || $this->startsWith($normalized, '/truefalse')) {
            $this->startTrueFalseMode($chatId, $user);

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

        if ($this->startsWith($normalized, '/stats') || $this->startsWith($normalized, '/statistics')) {
            $this->sendStatistics($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/referral') || $this->startsWith($normalized, '/invite')) {
            $this->sendReferralInfo($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/help')) {
            $this->sendHelp($chatId);

            return;
        }

        if ($this->startsWith($normalized, '/import_help')) {
            $this->sendImportHelp($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/import_facts')) {
            $this->startFactsImportMode($chatId, $user);

            return;
        }

        if ($this->startsWith($normalized, '/admin')) {
            $this->handleAdmin($chatId, $user);

            return;
        }

        $this->sendUnknown($chatId);
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
     * @param int|string $chatId
     */
    private function sendStart($chatId, ?User $user = null, ?string $commandText = null): void
    {
        // Проверяем, есть ли реферальный код в команде
        $refCode = null;
        if ($commandText && preg_match('/\/start\s+ref_([A-Z0-9]+)/i', $commandText, $matches)) {
            $refCode = $matches[1];
        }

        $text = implode("\n", [
            '⚔️ Добро пожаловать в «Битва знаний»!',
            '',
            'Готов проверить свою эрудицию? Попробуй наш новый Mini App для максимально комфортной игры!',
            '',
            '🎯 Что можно делать:',
            '• Участвовать в дуэлях 1 на 1',
            '• Играть в «Правда или ложь»',
            '• Следить за глобальным рейтингом',
            '• Изучать свою подробную статистику',
            '',
            'Нажми кнопку 🎮 Играть ниже, чтобы начать!',
        ]);

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getMainKeyboard(),
            ],
        ]);

        // Если есть реферальный код, обрабатываем его
        if ($refCode && $user) {
            $this->handleReferralCode($chatId, $user, $refCode);
        }
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

    private function startTrueFalseMode($chatId, ?User $user): void
    {
        if (!$user instanceof User) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось определить профиль. Нажми /start, чтобы синхронизировать данные и попробовать снова.',
                ],
            ]);

            return;
        }

        $record = $user->profile?->true_false_record ?? 0;

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => implode("\n", [
                    '🧠 <b>Правда или ложь</b>',
                    '',
                    'Читай утверждение и нажимай «Правда» или «Ложь».',
                    'Каждый правильный ответ увеличивает серию — побей свой рекорд!',
                    '',
                    sprintf('🏆 Твой рекорд: <b>%d</b>', $record),
                    '',
                    '⏱ На ответ даётся <b>15 секунд</b>.',
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Начать игру', 'callback_data' => 'tf:start'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function sendTrueFalseFactMessage($chatId, TrueFalseFact $fact, int $streak, ?User $user = null): void
    {
        $timeoutSeconds = 15;
        
        // Сохраняем время начала вопроса для проверки таймаута
        if ($user instanceof User) {
            $cacheKey = sprintf('tf_question_start:%d', $user->getKey());
            $this->cache->delete($cacheKey);
            $startTime = time();
            $this->cache->get($cacheKey, static fn () => $startTime);
        }

        $lines = [
            '🧠 <b>Правда или ложь</b>',
            sprintf('⏱ <b>%d сек.</b>', $timeoutSeconds),
        ];

        if ($streak > 0) {
            $lines[] = sprintf('🔥 Серия: %d', $streak);
        }

        $lines[] = '';
        $lines[] = htmlspecialchars($fact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines[] = '';
        $lines[] = 'Выбери ответ:';

        $keyboard = [
            [
                [
                    'text' => '✅ Правда',
                    'callback_data' => sprintf('tf:answer:%d:1', $fact->getKey()),
                ],
                [
                    'text' => '❌ Ложь',
                    'callback_data' => sprintf('tf:answer:%d:0', $fact->getKey()),
                ],
            ],
            [
                [
                    'text' => '⏭ Пропустить',
                    'callback_data' => 'tf:skip',
                ],
            ],
        ];

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
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
                'Выбери способ поиска соперника:',
                '',
                '👥 <b>Пригласить друга</b> — отправь ник соперника в формате @username',
                '🎲 <b>Случайный соперник</b> — найди игрока автоматически',
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
        
        // Устанавливаем постоянную клавиатуру через отдельное служебное сообщение
        $this->setMainKeyboard($chatId);
    }
    
    /**
     * Устанавливает постоянную клавиатуру с основными кнопками
     * @param int|string $chatId
     */
    private function setMainKeyboard($chatId): void
    {
        try {
            $response = $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => ' ',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
            
            $responseBody = (string) $response->getBody();
            $responseData = json_decode($responseBody, true);
            $messageId = $responseData['result']['message_id'] ?? null;
            
            if ($messageId !== null) {
                // Удаляем служебное сообщение через небольшую задержку
                sleep(1);
                $this->telegramClient->request('POST', 'deleteMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки при установке клавиатуры
        }
    }

    /**
     * @param int|string $chatId
     */
    private function sendHelp($chatId): void
    {
        $text = implode("\n", [
            '📋 <b>Список доступных команд</b>',
            '/start — старт и приветствие',
            '/help — список команд',
            '/play — быстрые раунды',
            '/duel — дуэли',
            '/profile — профиль',
            '/stats — статистика',
            '/leaderboard — рейтинг',
            '/truth — режим «Правда или ложь»',
            '/story — сюжет',
            '/referral — реферальная программа',
            '',
            'Если удобнее, просто нажми кнопку <b>🎮 Играть</b> ниже.',
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
     * @param int|string $chatId
     */
    private function sendImportHelp($chatId, ?User $user): void
    {
        if (!$user instanceof User || !$this->adminService->isAdmin($user)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⛔️ Команда доступна только администраторам.',
                ],
            ]);
            return;
        }

        $text = implode("\n", [
            '📥 <b>Импорт вопросов из файла</b>',
            '',
            'Отправь боту документ <code>.txt</code>, <code>.json</code> или <code>.ndjson</code>.',
            'После отправки бот сразу добавит вопросы в базу и пришлёт отчёт.',
            '',
            '<b>TXT формат (рекомендуется):</b>',
            '<code>CATEGORY: География',
            'Q: Столица Канады?',
            '+ Оттава',
            '- Торонто',
            '- Монреаль',
            '- Ванкувер',
            '',
            'Q: Самая длинная река в мире?',
            '+ Нил',
            '- Амазонка',
            '- Янцзы',
            '- Миссисипи</code>',
            '',
            'Правила:',
            '• Пустая строка разделяет вопросы',
            '• <code>+</code> — правильный ответ, <code>-</code> — неправильный',
            '• Категория должна существовать в БД',
            '• Если не указать <code>CATEGORY:</code> в блоке, будет использована последняя',
            '',
            'Можно указать категорию в подписи к файлу:',
            '<code>category: География</code>',
            '',
            '<b>Импорт фактов для «Правда или ложь»:</b>',
            '1) Отправь команду <code>/import_facts</code>',
            '2) Затем отправь файл <code>.txt</code>, <code>.json</code> или <code>.ndjson</code>',
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
    private function startFactsImportMode($chatId, ?User $user): void
    {
        if (!$user instanceof User || !$this->adminService->isAdmin($user)) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⛔️ Команда доступна только администраторам.',
                ],
            ]);
            return;
        }

        $cacheKey = sprintf('admin:import_facts_mode:%d', $user->getKey());
        try {
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, static function (ItemInterface $item) {
                $item->expiresAfter(600);
                return true;
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Не удалось включить режим импорта фактов', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        $text = implode("\n", [
            '📥 <b>Импорт фактов (Правда/Ложь)</b>',
            '',
            'Отправь следующим сообщением файл <code>.txt</code>, <code>.json</code> или <code>.ndjson</code>.',
            '',
            '<b>TXT формат:</b>',
            '<code>Солнце - это звезда.',
            'truth',
            'Солнце относится к звездам спектрального класса G.',
            '',
            'Эверест находится в Альпах.',
            'false',
            'Эверест находится в Гималаях.</code>',
            '',
            'Второй строкой укажи: <code>true/false</code> (или <code>правда/ложь</code>, <code>1/0</code>).',
            'Третья строка и далее - пояснение (опционально).',
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
    private function sendLeaderboard($chatId, ?User $user): void
    {
        $this->logger->debug('sendLeaderboard вызван', [
            'chat_id' => $chatId,
            'user_id' => $user?->getKey(),
        ]);
        
        try {
            $topPlayers = $this->userService->getTopPlayersByRating(10);
            
            // Фильтруем игроков с 0 рейтингом
            $topPlayers = array_values(array_filter($topPlayers, fn($entry) => $entry['rating'] > 0));
            
            $this->logger->debug('Получены топ игроки', [
                'count' => count($topPlayers),
            ]);
            
            if (empty($topPlayers)) {
                $this->logger->debug('Топ игроков пуст, отправка сообщения');
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '📊 Рейтинг пока пуст. Сыграй в дуэль, чтобы попасть в топ!',
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->getMainKeyboard(),
                    ],
                ]);
                return;
            }

            $lines = [
                '🏆 <b>ГЛОБАЛЬНЫЙ РЕЙТИНГ</b>',
                '',
            ];

            // Медали для топ-3
            $medals = ['🥇', '🥈', '🥉'];
            $position = 0;

            foreach ($topPlayers as $entry) {
                $position++;
                $playerUser = $entry['user'];
                $rating = $entry['rating'];
                $rank = $this->profileFormatter->getRankByRating($rating);

                // Имя пользователя
                $userName = $this->formatUserName($playerUser);

                // Медаль для топ-3, иначе номер
                if ($position <= 3) {
                    $positionDisplay = $medals[$position - 1];
                } else {
                    $positionDisplay = sprintf('%d.', $position);
                }

                // Позиция и имя на одной строке
                $lines[] = sprintf(
                    '%s <b>%s</b>',
                    $positionDisplay,
                    $userName
                );
                
                // Звание на отдельной строке (без эмодзи)
                $lines[] = $rank['name'];
                
                // Рейтинг на отдельной строке
                $lines[] = sprintf('   ⭐ Рейтинг: <b>%d</b>', $rating);
                $lines[] = '';
            }

            // Показываем позицию текущего пользователя, если он не в топе
            if ($user !== null) {
                $userPosition = $this->userService->getUserRatingPosition($user);
                
                if ($userPosition !== null) {
                    $user = $this->userService->ensureProfile($user);
                    $userProfile = $user->profile;
                    
                    if ($userProfile instanceof \QuizBot\Domain\Model\UserProfile) {
                        $userRating = (int) $userProfile->rating;
                        
                        // Не показываем позицию, если рейтинг = 0
                        if ($userRating > 0) {
                            $userRank = $this->profileFormatter->getRankByRating($userRating);
                            
                            // Проверяем, есть ли пользователь уже в топе
                            $inTop = false;
                            foreach ($topPlayers as $entry) {
                                if ($entry['user']->getKey() === $user->getKey()) {
                                    $inTop = true;
                                    break;
                                }
                            }
                            
                            if (!$inTop && $userPosition <= 100) {
                                $lines[] = '━━━━━━━━━━━━━━━━';
                                $lines[] = sprintf('📍 <b>Твоя позиция: %d</b>', $userPosition);
                                $lines[] = sprintf('%s | ⭐ <b>%d</b>', $userRank['name'], $userRating);
                            } elseif (!$inTop) {
                                $lines[] = '━━━━━━━━━━━━━━━━';
                                $lines[] = sprintf('📍 <b>Твоя позиция: %d+</b>', $userPosition);
                                $lines[] = sprintf('%s | ⭐ <b>%d</b>', $userRank['name'], $userRating);
                            }
                        }
                    }
                }
            }

            $this->logger->debug('Отправка рейтинга', [
                'lines_count' => count($lines),
                'text_length' => strlen(implode("\n", $lines)),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);

            $this->logger->debug('Рейтинг отправлен успешно');
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при отправке рейтинга', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Не удалось загрузить рейтинг. Попробуй позже.',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
        }
    }

    private function formatUserName(\QuizBot\Domain\Model\User $user): string
    {
        if (!empty($user->first_name) && !empty($user->last_name)) {
            return htmlspecialchars($user->first_name . ' ' . $user->last_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->first_name)) {
            return htmlspecialchars($user->first_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->username)) {
            return '@' . htmlspecialchars($user->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return sprintf('Игрок %d', (int) $user->getKey());
    }

    private function sendUnknown($chatId): void
    {
        $text = 'Я обрабатываю только команды из /help. Нажми 🎮 Играть, чтобы открыть приложение.';
        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $this->getMainKeyboard(),
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

        $text .= "\n\nПродолжай битву — запусти /duel!";

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '📊 Подробная статистика', 'callback_data' => 'stats:full'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param int|string $chatId
     */
    private function sendStatistics($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось загрузить статистику. Попробуйте /start.',
                ],
            ]);

            return;
        }

        try {
            $stats = $this->statisticsService->getFullStatistics($user);
            $text = $this->formatStatistics($stats);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось загрузить статистику', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "📊 <b>Статистика</b>\n\nНедостаточно данных. Сыграй несколько дуэлей, чтобы собрать статистику!",
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);

            return;
        }

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
     * @param array<string, mixed> $stats
     */
    private function formatStatistics(array $stats): string
    {
        $overview = $stats['overview'] ?? [];
        $strengths = $stats['strengths'] ?? [];
        $weaknesses = $stats['weaknesses'] ?? [];
        $bestDay = $stats['best_day'] ?? null;

        $lines = [
            '📊 <b>ТВОЯ СТАТИСТИКА</b>',
            '',
        ];

        // Общие показатели
        $lines[] = '🎯 <b>Общие показатели</b>';
        $accuracy = $overview['accuracy'] ?? 0;
        $avgTime = $overview['average_time'] ?? 0;
        $lines[] = sprintf('├ Точность: <b>%s%%</b>', $accuracy);
        $lines[] = sprintf('├ Среднее время: <b>%sс</b>', $avgTime);
        $lines[] = sprintf('├ Всего вопросов: <b>%d</b>', $overview['total_questions'] ?? 0);
        $lines[] = sprintf('├ Правильных: <b>%d</b>', $overview['correct_answers'] ?? 0);
        $lines[] = sprintf('└ Лучшая серия: <b>%d</b>', $overview['best_streak'] ?? 0);
        $lines[] = '';

        // Сильные стороны
        if (!empty($strengths)) {
            $lines[] = '💪 <b>Сильные стороны</b>';
            foreach ($strengths as $cat) {
                $icon = $cat['category_icon'] ?? '📚';
                $name = $cat['category_name'] ?? 'Неизвестно';
                $catAccuracy = $cat['accuracy'] ?? 0;
                $lines[] = sprintf('├ %s %s: <b>%s%%</b>', $icon, $name, $catAccuracy);
            }
            $lines[] = '';
        }

        // Слабые стороны
        if (!empty($weaknesses)) {
            $lines[] = '📚 <b>Нужно подтянуть</b>';
            foreach ($weaknesses as $cat) {
                $icon = $cat['category_icon'] ?? '📚';
                $name = $cat['category_name'] ?? 'Неизвестно';
                $catAccuracy = $cat['accuracy'] ?? 0;
                $lines[] = sprintf('├ %s %s: <b>%s%%</b>', $icon, $name, $catAccuracy);
            }
            $lines[] = '';
        }

        // Лучший день
        if ($bestDay !== null) {
            $dayName = $bestDay['day_name'] ?? $bestDay['day'] ?? '';
            $dayAccuracy = $bestDay['accuracy'] ?? 0;
            $baseAccuracy = $overview['accuracy'] ?? 0;
            $diff = round($dayAccuracy - $baseAccuracy);
            $diffStr = $diff > 0 ? "+{$diff}%" : "{$diff}%";
            
            $lines[] = '⏰ <b>Лучшее время для игры</b>';
            $lines[] = sprintf('└ 📅 %s (%s к точности)', $dayName, $diffStr);
            $lines[] = '';
        }

        // Серия побед в дуэлях
        $duelStreak = $overview['best_duel_win_streak'] ?? 0;
        if ($duelStreak > 0) {
            $lines[] = sprintf('🔥 <b>Лучшая серия побед в дуэлях: %d</b>', $duelStreak);
        }

        return implode("\n", $lines);
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

        // Не создаем дуэль сразу - только показываем меню выбора
        // Дуэль будет создана только при выборе "Пригласить друга" или "Случайный соперник"
        try {
            $this->sendDuelMenu($chatId, null);
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
                                'text' => '➕ Добавить вопросы',
                                'callback_data' => 'admin:add_question',
                            ],
                        ],
                        [
                            [
                                'text' => '⚔️ Завершить все активные дуэли',
                                'callback_data' => 'admin:finish_all_duels',
                            ],
                        ],
                        [
                            [
                                'text' => '🎯 Завершить дуэль по нику',
                                'callback_data' => 'admin:finish_duel_by_username',
                            ],
                        ],
                        [
                            [
                                'text' => '🔄 Сбросить рейтинг всех до 0',
                                'callback_data' => 'admin:reset_ratings',
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

    /**
     * Отправляет информацию о реферальной программе
     */
    private function sendReferralInfo($chatId, ?User $user): void
    {
        try {
            $this->logger->debug('sendReferralInfo: начало', ['chat_id' => $chatId, 'user_id' => $user?->getKey()]);
            
            if (!$user instanceof User) {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => 'Не удалось загрузить профиль. Попробуй /start.',
                    ],
                ]);
                return;
            }

            $this->logger->debug('sendReferralInfo: получаем статистику');
            $stats = $this->referralService->getReferralStats($user);
            
            $this->logger->debug('sendReferralInfo: получаем ссылку');
            $link = $this->referralService->getReferralLink($user);

            $this->logger->debug('sendReferralInfo: формируем текст', ['stats' => $stats]);

            $text = implode("\n", [
                '🎁 <b>Приглашай друзей!</b>',
                '',
                sprintf('Твой реферальный код: <code>%s</code>', $stats['referral_code']),
                '',
                '🎯 <b>Что получишь:</b>',
                '• <b>100 монет</b> когда друг сыграет 3 игры',
                '• <b>50 опыта</b> за каждого активного друга',
                '• Бонусы за количество приглашенных!',
                '',
                '💫 <b>Что получит друг:</b>',
                '• <b>50 монет</b> сразу при регистрации',
                '• <b>25 опыта</b> в подарок',
                '',
                '📊 <b>Твоя статистика:</b>',
                sprintf('👥 Приглашено: <b>%d</b> друзей', $stats['total_referrals']),
                sprintf('✅ Активных: <b>%d</b>', $stats['active_referrals']),
                sprintf('💰 Заработано: <b>%d</b> монет', $stats['total_coins_earned']),
                sprintf('⭐ Получено опыта: <b>%d</b>', $stats['total_exp_earned']),
            ]);

            if ($stats['next_milestone']) {
                $m = $stats['next_milestone'];
                $text .= implode("\n", [
                    '',
                    '🏆 <b>Следующая награда:</b>',
                    sprintf('%s — %d друзей', $m['title'], $m['referrals_needed']),
                    sprintf('Прогресс: %d/%d', $m['progress'], $m['referrals_needed']),
                    sprintf('Награда: %d монет + %d опыта', $m['reward_coins'], $m['reward_experience']),
                ]);
            }

            $this->logger->debug('sendReferralInfo: отправляем сообщение');

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '📤 Поделиться ссылкой',
                                    'url' => sprintf('https://t.me/share/url?url=%s&text=%s', 
                                        urlencode($link),
                                        urlencode('🎮 Присоединяйся к Битве знаний! Получи 50 монет в подарок!')
                                    ),
                                ],
                            ],
                            [
                                ['text' => '👥 Мои рефералы', 'callback_data' => 'ref:list'],
                            ],
                        ],
                    ],
                ],
            ]);
            
            $this->logger->debug('sendReferralInfo: успешно завершено');
        } catch (\Throwable $e) {
            $this->logger->error('sendReferralInfo: ошибка', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Отправляем сообщение об ошибке пользователю
            try {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '😔 Произошла ошибка при загрузке реферальной информации. Попробуйте позже.',
                    ],
                ]);
            } catch (\Throwable $e2) {
                $this->logger->error('sendReferralInfo: не удалось отправить сообщение об ошибке', [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }

    /**
     * Обрабатывает реферальный код при первом запуске
     */
    private function handleReferralCode($chatId, User $user, string $code): void
    {
        $result = $this->referralService->applyReferralCode($user, $code);
        
        if ($result['success']) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", [
                        '🎉 <b>Отлично!</b>',
                        '',
                        'Ты использовал реферальный код!',
                        sprintf('Получено: <b>%d монет</b> и <b>%d опыта</b>', 
                            $result['reward_coins'], 
                            $result['reward_experience']
                        ),
                        '',
                        'Сыграй 3 игры, чтобы твой друг тоже получил награду! 🎁',
                    ]),
                    'parse_mode' => 'HTML',
                ],
            ]);
        } else {
            $this->logger->warning('Не удалось применить реферальный код', [
                'user_id' => $user->getKey(),
                'code' => $code,
                'error' => $result['error'],
            ]);
        }
    }
}
