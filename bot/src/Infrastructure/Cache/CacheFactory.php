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

    private string $redisDsn;

    private string $redisNamespace;

    public function __construct(string $driver, string $storagePath, string $redisDsn = '', string $redisNamespace = 'quiz_bot')
    {
        $this->driver = $driver;
        $this->storagePath = $storagePath;
        $this->redisDsn = $redisDsn;
        $this->redisNamespace = $redisNamespace !== '' ? $redisNamespace : 'quiz_bot';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function create(): CacheInterface
    {
        if ($this->driver === 'redis' && $this->redisDsn !== '') {
            try {
                $connection = RedisAdapter::createConnection($this->redisDsn);
                return new RedisAdapter($connection, $this->redisNamespace, 0);
            } catch (\Throwable $e) {
                // Fallback ниже: если Redis недоступен/не настроен, используем filesystem.
            }
        }

        if ($this->driver === 'filesystem') {
            return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
        }

        if ($this->driver === 'redis') {
            return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
        }

        if ($this->driver === 'array') {
            return new ArrayAdapter();
        }

        return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
    }
}
