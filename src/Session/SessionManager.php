<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use TGbotPHP\Cache\CacheInterface;

class SessionManager
{
    private const SESSION_TTL = 3600;

    public function __construct(private CacheInterface $cache) {}

    public function startSession(int $userId): string
    {
        $sessionId = bin2hex(random_bytes(16));
        $this->cache->put(
            "session:$sessionId",
            ['user_id' => $userId, 'created_at' => time()],
            self::SESSION_TTL
        );
        return $sessionId;
    }

    public function getSession(string $sessionId): ?array
    {
        return $this->cache->get("session:$sessionId");
    }

    public function setSessionData(string $sessionId, string $key, mixed $value): void
    {
        $session = $this->getSession($sessionId);
        if ($session) {
            $session[$key] = $value;
            $this->cache->put("session:$sessionId", $session, self::SESSION_TTL);
        }
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
