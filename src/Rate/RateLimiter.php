<?php

declare(strict_types=1);

namespace TGbotPHP\Rate;

use TGbotPHP\Cache\CacheInterface;

class RateLimiter
{
    public function __construct(private CacheInterface $cache) {}

    public function limit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $current = (int)$this->cache->get($key, 0);

        if ($current >= $maxRequests) {
            return false;
        }

        $this->cache->put($key, $current + 1, $windowSeconds);
        return true;
    }

    public function reset(string $key): void
    {
        $this->cache->forget($key);
    }

    public function remaining(string $key, int $maxRequests): int
    {
        $current = (int)$this->cache->get($key, 0);
        return max(0, $maxRequests - $current);
    }
}
