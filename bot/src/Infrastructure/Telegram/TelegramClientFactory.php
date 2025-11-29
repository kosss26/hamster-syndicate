<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class TelegramClientFactory
{
    private string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    public function create(): ClientInterface
    {
        return new Client([
            'base_uri' => sprintf('https://api.telegram.org/bot%s/', $this->botToken),
            'timeout' => 5.0,
        ]);
    }
}

