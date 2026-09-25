<?php

declare(strict_types=1);

namespace TGbotPHP\Rate;

use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Support\Value;

/**
 * Fixed-window rate limiter
 */
class RateLimiter
{
    public function __construct(private CacheInterface $cache) {}

    /**
     * Register a hit; returns false when the limit for the current window is reached
     */
    public function limit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $allowed = false;

        $this->cache->update(
            $this->cacheKey($key),
            static function (mixed $stored) use ($maxRequests, $windowSeconds, &$allowed): array {
                $now = time();
                $window = self::decode($stored);

                if ($window === null || $window['reset_at'] <= $now) {
                    $window = ['count' => 0, 'reset_at' => $now + $windowSeconds];
                }

                if ($window['count'] < $maxRequests) {
                    $window['count']++;
                    $allowed = true;
                }

                return $window;
            },
            $windowSeconds,
        );

        return $allowed;
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
     * @param (callable(stdClass, mixed...): mixed)|null $onLimited Called for dropped updates
     * @return \Closure(stdClass, mixed...): bool
     */
    public function middleware(int $maxRequests, int $windowSeconds, ?callable $onLimited = null): \Closure
    {
        return function (stdClass $update, mixed ...$args) use ($maxRequests, $windowSeconds, $onLimited): bool {
            $user = UpdateParser::getUser($update);

            $userId = Value::id(Value::path($user, 'id'));

            if ($userId === null) {
                return true;
            }

            if ($this->limit('user:' . $userId, $maxRequests, $windowSeconds)) {
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
        return self::decode($this->cache->get($this->cacheKey($key)));
    }

    /**
     * @return array{count: int, reset_at: int}|null
     */
    private static function decode(mixed $window): ?array
    {
        if (!is_array($window)) {
            return null;
        }

        $count = Value::nullableInt($window['count'] ?? null);
        $resetAt = Value::nullableInt($window['reset_at'] ?? null);

        return $count !== null && $resetAt !== null ? ['count' => $count, 'reset_at' => $resetAt] : null;
    }

    private function cacheKey(string $key): string
    {
        return 'rate:' . $key;
    }
}
