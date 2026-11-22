<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use InvalidArgumentException;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Support\Value;

/**
 * Simple user sessions stored in a cache for one hour.
 */
class SessionManager
{
    /** Sessions expire after one hour of inactivity. */
    private const int SESSION_TTL = 3600;

    /**
     * @param CacheInterface $cache Cache holding the sessions
     */
    public function __construct(private CacheInterface $cache)
    {
        // Dependency promoted to property.
    }

    /** Create a new session for a user and return its opaque identifier. */
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
     * @return array<string, mixed>|null The session, or null when missing or malformed
     */
    public function getSession(string $sessionId): ?array
    {
        $session = $this->cache->get("session:$sessionId");

        return Value::isMap($session) ? $session : null;
    }

    /**
     * Store a value in a session.
     *
     * @throws InvalidArgumentException When the key is reserved for session metadata
     */
    public function setSessionData(string $sessionId, string $key, mixed $value): void
    {
        if (in_array($key, ['user_id', 'created_at'], true)) {
            throw new InvalidArgumentException('Session identity and creation time are reserved');
        }

        $this->cache->update(
            "session:$sessionId",
            static fn(mixed $session): ?array => Value::isMap($session) ? [$key => $value] + $session : null,
            self::SESSION_TTL,
        );
    }

    /** Read a value from a session, returning the default when absent. */
    public function getSessionData(string $sessionId, string $key, mixed $default = null): mixed
    {
        $session = $this->getSession($sessionId);

        return $session[$key] ?? $default;
    }

    /** Remove a session. */
    public function endSession(string $sessionId): void
    {
        $this->cache->forget("session:$sessionId");
    }
}
