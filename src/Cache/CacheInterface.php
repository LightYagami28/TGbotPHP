<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Read, change and write an entry as one step
     *
     * Concurrent updates of the same key must not overwrite each other: with
     * webhooks, two updates from the same user can be processed at the same
     * time. The callback receives the current value (null when missing) and
     * returns the new one; returning null removes the entry.
     *
     * @param callable(mixed): mixed $callback
     * @return mixed The stored value
     */
    public function update(string $key, callable $callback, ?int $ttl = null): mixed;

    public function forget(string $key): void;

    public function flush(): void;

    public function has(string $key): bool;
}
