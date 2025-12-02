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
use QuizBot\Domain\Model\User;

final class MessageHandler
{
    private ClientInterface $telegramClient;

    private Logger $logger;

    private CacheInterface $cache;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private ProfileFormatter $profileFormatter;

    private StoryService $storyService;

    private AdminService $adminService;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        CacheInterface $cache,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter,
        AdminService $adminService
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
                $this->adminService
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
            
            // Обработка кнопок клавиатуры (проверяем первыми, до создания CommandHandler)
            if ($text === '⚔️ Дуэль' || $text === 'Дуэль') {
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                    $this->adminService
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
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                    $this->adminService
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
                $commandHandler = new CommandHandler(
                    $this->telegramClient,
                    $this->logger,
                    $this->userService,
                    $this->duelService,
                    $this->gameSessionService,
                    $this->storyService,
                    $this->profileFormatter,
                    $this->adminService
                );
                $commandHandler->handle([
                    'chat_id' => $chatId,
                    'command' => '/leaderboard',
                    'from' => $from,
                    'user' => $user,
                ]);
                return;
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
                $this->adminService
            );

            if ($user instanceof User && $this->looksLikeUsernameInput($text)) {
                if ($commandHandler->handleDuelUsernameInvite($chatId, $user, $text)) {
                    return;
                }
            }
        }

        $this->sendWelcome($chatId);
    }

    /**
     * @param int|string $chatId
     */
    private function sendWelcome($chatId): void
    {
        $text = implode("\n", [
            '👋 Привет! Это викторина «Битва знаний».',
            'Доступны дуэли с друзьями и подробный профиль.',
            'Команды: /duel, /profile, /help.',
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
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    private function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    private function looksLikeUsernameInput(string $text): bool
    {
        return (bool) preg_match('/^@[A-Za-z0-9_]{5,}$/', $text);
    }
}

