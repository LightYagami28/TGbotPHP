<?php

declare(strict_types=1);

namespace TGbotPHP\Rate;

use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\UpdateParser;

/**
 * Fixed-window rate limiter
 */
class RateLimiter
{
    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Register a hit; returns false when the limit for the current window is reached
     */
    public function limit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $now = time();
        $window = $this->window($key);

        if ($window === null || $window['reset_at'] <= $now) {
            $window = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        if ($window['count'] >= $maxRequests) {
            return false;
        }

        $window['count']++;
        $this->cache->put($this->cacheKey($key), $window, max(1, $window['reset_at'] - $now));

        return true;
    }

    public function reset(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }

    public function remaining(string $key, int $maxRequests): int
    {
        $window = $this->window($key);

        if ($window === null || $window['reset_at'] <= time()) {
            return $maxRequests;
        }

        return max(0, $maxRequests - $window['count']);
    }

    /**
     * Seconds until the current window resets
     */
    public function availableIn(string $key): int
    {
        $window = $this->window($key);

        return $window === null ? 0 : max(0, $window['reset_at'] - time());
    }

    /**
     * Middleware limiting each user to $maxRequests updates per $windowSeconds
     *
     *     $bot->middleware($limiter->middleware(5, 10, fn($update, $bot) => ...));
     *
     * @param callable|null $onLimited fn(stdClass $update, mixed ...$args) called for dropped updates
     */
    public function middleware(int $maxRequests, int $windowSeconds, ?callable $onLimited = null): callable
    {
        return function (stdClass $update, mixed ...$args) use ($maxRequests, $windowSeconds, $onLimited): bool {
            $user = UpdateParser::getUser($update);

            if ($user === null || !isset($user->id)) {
                return true;
            }

            if ($this->limit('user:' . $user->id, $maxRequests, $windowSeconds)) {
                return true;
            }

            if ($onLimited !== null) {
                $onLimited($update, ...$args);
            }

            return false;
        };
    }

    /**
     * @return array{count: int, reset_at: int}|null
     */
    private function window(string $key): ?array
    {
        $window = $this->cache->get($this->cacheKey($key));

        if (!is_array($window) || !isset($window['count'], $window['reset_at'])) {
            return null;
        }

        return ['count' => (int) $window['count'], 'reset_at' => (int) $window['reset_at']];
    }

    private function cacheKey(string $key): string
    {
        return 'rate:' . $key;
    }
}
