<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class CacheFactory
{
    private string $driver;

    private string $storagePath;

    public function __construct(string $driver, string $storagePath)
    {
        $this->driver = $driver;
        $this->storagePath = $storagePath;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function create(): CacheInterface
    {
        if ($this->driver === 'filesystem') {
            return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
        }

        return new ArrayAdapter();
    }
}

