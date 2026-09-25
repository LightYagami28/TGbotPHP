<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Support\Value;

class SessionManager
{
    private const int SESSION_TTL = 3600;

    public function __construct(private CacheInterface $cache) {}

    public function startSession(int $userId): string
    {
        $sessionId = bin2hex(random_bytes(16));
        $this->cache->put(
            "session:$sessionId",
            ['user_id' => $userId, 'created_at' => time()],
            self::SESSION_TTL,
        );
        return $sessionId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSession(string $sessionId): ?array
    {
        $session = $this->cache->get("session:$sessionId");

        return Value::isMap($session) ? $session : null;
    }

    public function setSessionData(string $sessionId, string $key, mixed $value): void
    {
        $this->cache->update(
            "session:$sessionId",
            static fn(mixed $session): ?array => Value::isMap($session) ? [$key => $value] + $session : null,
            self::SESSION_TTL,
        );
    }

    public function getSessionData(string $sessionId, string $key, mixed $default = null): mixed
    {
        $session = $this->getSession($sessionId);
        return $session[$key] ?? $default;
    }

    public function endSession(string $sessionId): void
    {
        $this->cache->forget("session:$sessionId");
    }
}
