<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

class ArrayCache implements CacheInterface
{
    private array $store = [];
    private array $expiration = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = $value;

        if ($ttl !== null) {
            $this->expiration[$key] = time() + $ttl;
        }
    }

    public function forget(string $key): void
    {
        unset($this->store[$key], $this->expiration[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
        $this->expiration = [];
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        if (isset($this->expiration[$key]) && time() > $this->expiration[$key]) {
            $this->forget($key);
            return false;
        }

        return true;
    }
}
