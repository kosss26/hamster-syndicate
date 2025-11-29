<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use QuizBot\Presentation\Updates\Handlers\CommandHandler;
use QuizBot\Presentation\Updates\Handlers\MessageHandler;
use QuizBot\Presentation\Updates\Handlers\CallbackQueryHandler;
use Symfony\Contracts\Cache\CacheInterface;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StoryService;

final class UpdateRouter
{
    private ClientInterface $telegramClient;

    private Logger $logger;

    private CacheInterface $cache;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private StoryService $storyService;

    private ProfileFormatter $profileFormatter;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        CacheInterface $cache,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter
    ) {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->profileFormatter = $profileFormatter;
    }

    /**
     * @param array<string, mixed> $update
     */
    public function route(array $update): void
    {
        $this->logger->debug('Получено обновление', $update);

        if (isset($update['message'])) {
            $messageHandler = new MessageHandler(
                $this->telegramClient,
                $this->logger,
                $this->cache,
                $this->userService,
                $this->duelService,
                $this->gameSessionService,
                $this->storyService,
                $this->profileFormatter
            );
            $messageHandler->handle($update['message']);

            return;
        }

        if (isset($update['callback_query'])) {
            $callbackHandler = new CallbackQueryHandler(
                $this->telegramClient,
                $this->logger,
                $this->cache,
                $this->userService,
                $this->duelService,
                $this->gameSessionService,
                $this->storyService,
                $this->profileFormatter
            );
            $callbackHandler->handle($update['callback_query']);

            return;
        }

        if (isset($update['command'])) {
            $commandHandler = new CommandHandler(
                $this->telegramClient,
                $this->logger,
                $this->userService,
                $this->duelService,
                $this->gameSessionService,
                $this->storyService,
                $this->profileFormatter
            );
            $commandHandler->handle($update['command']);
        }
    }
}

