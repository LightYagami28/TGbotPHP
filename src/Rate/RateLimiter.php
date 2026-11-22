<?php

declare(strict_types=1);

namespace TGbotPHP\Rate;

use Closure;
use InvalidArgumentException;
use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Support\Value;

/**
 * Fixed-window rate limiter backed by a cache.
 *
 * The current window is stored under a prefixed key, so the limiter works
 * across requests when a persistent cache is used.
 */
class RateLimiter
{
    /**
     * @param CacheInterface $cache Cache holding the current windows
     */
    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Register a hit and return whether it is allowed in the current window.
     *
     * @param string $key Identifier of the caller, e.g. "user:42"
     * @param int $maxRequests Maximum hits allowed per window
     * @param int $windowSeconds Length of a window in seconds
     *
     * @throws InvalidArgumentException When the limits are not positive
     */
    public function limit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        self::assertWindow($maxRequests, $windowSeconds);
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

    /** Discard the current window for a key. */
    public function reset(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }

    /** Number of hits still allowed in the current window. */
    public function remaining(string $key, int $maxRequests): int
    {
        $window = $this->window($key);

        if ($window === null || $window['reset_at'] <= time()) {
            return $maxRequests;
        }

        return max(0, $maxRequests - $window['count']);
    }

    /** Seconds until the current window resets. */
    public function availableIn(string $key): int
    {
        $window = $this->window($key);

        return $window === null ? 0 : max(0, $window['reset_at'] - time());
    }

    /**
     * Middleware limiting each user to $maxRequests updates per $windowSeconds.
     *
     *     $bot->middleware($limiter->middleware(5, 10, fn($update, $bot) => ...));
     *
     * @param (callable(stdClass, mixed...): mixed)|null $onLimited Called for dropped updates
     * @return Closure(stdClass, mixed...): bool
     *
     * @throws InvalidArgumentException When the limits are not positive
     */
    public function middleware(int $maxRequests, int $windowSeconds, ?callable $onLimited = null): Closure
    {
        self::assertWindow($maxRequests, $windowSeconds);

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
     * Normalise a stored window, rejecting malformed or partial values.
     *
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

    /** Prefix a caller key to avoid collisions with other cache users. */
    private function cacheKey(string $key): string
    {
        return 'rate:' . $key;
    }

    /**
     * @throws InvalidArgumentException When the limits are not positive
     */
    private static function assertWindow(int $maxRequests, int $windowSeconds): void
    {
        if ($maxRequests <= 0 || $windowSeconds <= 0) {
            throw new InvalidArgumentException(
                'Rate limit requires a positive number of requests and a positive window',
            );
        }
    }
}
