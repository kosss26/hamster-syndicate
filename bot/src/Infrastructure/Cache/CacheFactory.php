<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class CacheFactory
{
    private string $driver;
    private string $storagePath;
    private ?string $redisUrl;

    public function __construct(string $driver, string $storagePath, ?string $redisUrl = null)
    {
        $this->driver = $driver;
        $this->storagePath = $storagePath;
        $this->redisUrl = $redisUrl;

        if ($this->driver === 'filesystem' && !is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function create(): CacheInterface
    {
        if ($this->driver === 'redis' && $this->redisUrl) {
            $client = RedisAdapter::createConnection($this->redisUrl);
            return new RedisAdapter($client);
        }

        if ($this->driver === 'filesystem') {
            return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
        }

        return new ArrayAdapter();
    }
}

