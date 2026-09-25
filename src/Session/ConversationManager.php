<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Support\Payload;
use TGbotPHP\Support\Value;

/**
 * Conversation state per chat and user, for multi-step dialogs
 *
 * Use a persistent cache (FileCache, Redis...) with webhooks: every webhook
 * request runs in a new PHP process.
 */
final readonly class ConversationManager
{
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 3600,
        private string $prefix = 'conversation:',
    ) {}

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

    /**
     * State and data of the conversation a message belongs to
     *
     * @return array{?string, array<string, mixed>}
     */
    public function stateOf(stdClass $message): array
    {
        $chatId = Payload::requireChatId($message);
        $userId = Payload::userId($message);
        $state = $this->getState($chatId, $userId);

        return [$state, $state !== null ? $this->getData($chatId, $userId) : []];
    }

    /**
     * Move the sender of a message (or of a callback query) into a state
     *
     * @param array<string, mixed> $data
     */
    public function enter(stdClass $payload, string $state, array $data = []): void
    {
        $this->setState(Payload::requireChatId($payload), Payload::userId($payload), $state, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(stdClass $payload, array $data): void
    {
        $this->updateData(Payload::requireChatId($payload), Payload::userId($payload), $data);
    }

    public function leave(stdClass $payload): void
    {
        $this->clear(Payload::requireChatId($payload), Payload::userId($payload));
    }

    private function key(int|string $chatId, int|string|null $userId): string
    {
        return $this->prefix . $chatId . ':' . ($userId ?? '*');
    }
}
