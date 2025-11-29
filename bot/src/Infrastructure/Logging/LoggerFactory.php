<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

final class LoggerFactory
{
    private string $environment;

    private string $defaultChannel;

    private string $logDir;

    public function __construct(string $environment, string $defaultChannel, string $logDir)
    {
        $this->environment = $environment;
        $this->defaultChannel = $defaultChannel;
        $this->logDir = $logDir;

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
    }

    public function create(string $channel, HandlerInterface $handler): Logger
    {
        $logger = new Logger($channel ?: $this->defaultChannel);

        $handler->setFormatter(new LineFormatter(null, null, true));
        $logger->pushHandler($handler);

        if ($this->environment !== 'production') {
            $logger->pushProcessor(fn (array $record) => $record);
        }

        return $logger;
    }
}

