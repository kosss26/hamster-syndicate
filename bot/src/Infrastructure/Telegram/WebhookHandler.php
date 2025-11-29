<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Telegram;

use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use QuizBot\Infrastructure\Cache\CacheFactory;
use QuizBot\Infrastructure\Config\Config;
use QuizBot\Presentation\Updates\UpdateRouter;
use Symfony\Contracts\Cache\CacheInterface;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StoryService;
use QuizBot\Application\Services\MessageFormatter;

final class WebhookHandler
{
    private CacheInterface $cache;

    private Config $config;

    private Logger $logger;

    private TelegramClientFactory $clientFactory;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private ProfileFormatter $profileFormatter;

    private StoryService $storyService;

    private MessageFormatter $messageFormatter;

    public function __construct(
        Config $config,
        Logger $logger,
        TelegramClientFactory $clientFactory,
        CacheFactory $cacheFactory,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        ProfileFormatter $profileFormatter,
        MessageFormatter $messageFormatter
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->clientFactory = $clientFactory;
        $this->cache = $cacheFactory->create();
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->profileFormatter = $profileFormatter;
        $this->messageFormatter = $messageFormatter;
    }

    public function handle(ServerRequestInterface $request): void
    {
        $payload = $request->getParsedBody();

        if (!\is_array($payload)) {
            $this->logger->warning('Получен некорректный payload от Telegram');

            return;
        }

        $secret = $request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token');
        $expected = $this->config->get('TELEGRAM_WEBHOOK_SECRET');

        if ($expected && $secret !== $expected) {
            $this->logger->warning('Некорректный секрет вебхука');

            return;
        }

        $updateRouter = new UpdateRouter(
            $this->clientFactory->create(),
            $this->logger,
            $this->cache,
            $this->userService,
            $this->duelService,
            $this->gameSessionService,
            $this->storyService,
            $this->profileFormatter,
            $this->messageFormatter
        );

        $updateRouter->route($payload);
    }
}

