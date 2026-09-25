<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Support\Value;

/**
 * Per chat/user conversation state for multi-step dialogs
 *
 * Use a persistent cache (FileCache, Redis, ...) in webhook mode: every
 * webhook request runs in a fresh PHP process.
 */
final class ConversationManager
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'conversation:'
    ) {
    }

    public function getState(int|string $chatId, int|string|null $userId = null): ?string
    {
        $entry = $this->cache->get($this->key($chatId, $userId));

        return is_array($entry) ? Value::nullableString($entry['state'] ?? null) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(int|string $chatId, int|string|null $userId = null): array
    {
        $entry = $this->cache->get($this->key($chatId, $userId));

        return is_array($entry) ? Value::map($entry['data'] ?? null) : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setState(int|string $chatId, int|string|null $userId, string $state, array $data = []): void
    {
        $this->cache->put($this->key($chatId, $userId), ['state' => $state, 'data' => $data], $this->ttl);
    }

    /**
     * Merge data into the current conversation, keeping its state
     *
     * @param array<string, mixed> $data
     */
    public function updateData(int|string $chatId, int|string|null $userId, array $data): void
    {
        $state = $this->getState($chatId, $userId);

        if ($state !== null) {
            $this->setState($chatId, $userId, $state, array_merge($this->getData($chatId, $userId), $data));
        }
    }

    public function clear(int|string $chatId, int|string|null $userId = null): void
    {
        $this->cache->forget($this->key($chatId, $userId));
    }

    private function key(int|string $chatId, int|string|null $userId): string
    {
        return $this->prefix . $chatId . ':' . ($userId ?? '*');
    }
}
