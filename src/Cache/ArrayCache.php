<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

/**
 * In-memory cache, lost at the end of the PHP process
 *
 * Suitable for long polling bots and tests. Use FileCache (or your own
 * CacheInterface implementation) for webhooks.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    /** @var array<string, int> */
    private array $expiration = [];

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key];
    }

    #[\Override]
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = $value;

        if ($ttl !== null) {
            $this->expiration[$key] = time() + $ttl;
        } else {
            unset($this->expiration[$key]);
        }
    }

    #[\Override]
    public function forget(string $key): void
    {
        unset($this->store[$key], $this->expiration[$key]);
    }

    #[\Override]
    public function flush(): void
    {
        $this->store = [];
        $this->expiration = [];
    }

    #[\Override]
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }

        if (isset($this->expiration[$key]) && time() >= $this->expiration[$key]) {
            $this->forget($key);
            return false;
        }

        return true;
    }
}
