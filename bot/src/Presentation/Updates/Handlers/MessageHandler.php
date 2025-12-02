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
                $this->logger->debug('Обработка кнопки Профиль');
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
                $this->logger->debug('Обработка кнопки Рейтинг');
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
            
            $this->logger->debug('Текст не соответствует кнопкам клавиатуры', [
                'text' => $text,
                'is_duel' => ($text === '⚔️ Дуэль' || $text === 'Дуэль'),
                'is_profile' => ($text === '📊 Профиль' || $text === 'Профиль'),
                'is_rating' => ($text === '🏆 Рейтинг' || $text === 'Рейтинг'),
            ]);

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

            // Проверяем, ожидает ли админ ввода юзернейма для завершения дуэли
            if ($user instanceof User && $this->looksLikeUsernameInput($text)) {
                $cacheKey = sprintf('admin:finish_duel_username:%d', $user->getKey());
                if ($this->cache->hasItem($cacheKey)) {
                    // Это запрос на завершение дуэли по нику
                    $this->cache->delete($cacheKey);
                    $this->handleAdminFinishDuelByUsername($chatId, $user, $text);
                    return;
                }

                // Обычная обработка приглашения в дуэль
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

