<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Security;

use Symfony\Contracts\Cache\CacheInterface;

class RateLimiter
{
    private CacheInterface $cache;
    private int $limit;
    private int $period;

    public function __construct(CacheInterface $cache, int $limit = 60, int $period = 60)
    {
        $this->cache = $cache;
        $this->limit = $limit;
        $this->period = $period;
    }

    public function check(string $identifier): bool
    {
        $key = 'rate_limit:' . $identifier;
        
        try {
            $current = $this->cache->get($key, function ($item) {
                $item->expiresAfter($this->period);
                return 0;
            });

            if ($current >= $this->limit) {
                return false;
            }

            // Increment manually since CacheInterface doesn't support increment
            // This is a naive implementation, atomic increment would be better (e.g. Redis incr)
            // But for now we rely on simple replacement
            $this->cache->delete($key);
            $this->cache->get($key, function ($item) use ($current) {
                $item->expiresAfter($this->period);
                return $current + 1;
            });
            
            return true;
        } catch (\Throwable $e) {
            // If cache fails, allow request (fail open)
            return true;
        }
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPeriod(): int
    {
        return $this->period;
    }
}
