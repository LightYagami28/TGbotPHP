<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

/**
 * Minimal key/value cache contract used by sessions, conversations and rate limits.
 */
interface CacheInterface
{
    /** Read an entry, returning $default when it is missing or expired. */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Write an entry.
     *
     * @param int|null $ttl Time to live in seconds; null means no expiration
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Read, change and write an entry as one atomic step.
     *
     * Concurrent updates of the same key must not overwrite each other: with
     * webhooks, two updates from the same user can be processed at the same
     * time. The callback receives the current value (null when missing) and
     * returns the new one; returning null removes the entry.
     *
     * @param callable(mixed): mixed $callback Callback receiving the current value
     * @param int|null $ttl Time to live in seconds; null means no expiration
     *
     * @return mixed The stored value
     */
    public function update(string $key, callable $callback, ?int $ttl = null): mixed;

    /** Remove an entry if present. */
    public function forget(string $key): void;

    /** Remove every entry. */
    public function flush(): void;

    /** Whether a non-expired entry exists for the key. */
    public function has(string $key): bool;
}
