<?php

declare(strict_types=1);

namespace TGbotPHP\Session;

use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Support\Payload;
use TGbotPHP\Support\Value;

/**
 * Conversation state per chat and user, for multi-step dialogs.
 *
 * Use a persistent cache (FileCache, Redis, ...) with webhooks: every webhook
 * request runs in a new PHP process.
 */
final readonly class ConversationManager
{
    /**
     * @param CacheInterface $cache Cache holding the conversation state
     * @param int $ttl Seconds of inactivity before a conversation expires
     * @param string $prefix Prefix of the cache keys
     */
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 3600,
        private string $prefix = 'conversation:',
    )
    {
    }

    /** Name of the state a conversation is currently in, when any. */
    public function getState(int|string $chatId, int|string|null $userId = null): ?string
    {
        $entry = $this->cache->get($this->key($chatId, $userId));

        return is_array($entry) ? Value::nullableString($entry['state'] ?? null) : null;
    }

    /**
     * @return array<string, mixed> Data collected so far in the conversation
     */
    public function getData(int|string $chatId, int|string|null $userId = null): array
    {
        $entry = $this->cache->get($this->key($chatId, $userId));

        return is_array($entry) ? Value::map($entry['data'] ?? null) : [];
    }

    /**
     * @param array<string, mixed> $data Initial or replacement data
     */
    public function setState(int|string $chatId, int|string|null $userId, string $state, array $data = []): void
    {
        $this->cache->put($this->key($chatId, $userId), ['state' => $state, 'data' => $data], $this->ttl);
    }

    /**
     * Merge data into the current conversation, keeping its state.
     *
     * @param array<string, mixed> $data Data to add or overwrite
     */
    public function updateData(int|string $chatId, int|string|null $userId, array $data): void
    {
        $this->cache->update(
            $this->key($chatId, $userId),
            static function (mixed $entry) use ($data): ?array {
                if (!is_array($entry) || Value::nullableString($entry['state'] ?? null) === null) {
                    return null;
                }

                return ['state' => $entry['state'], 'data' => array_merge(Value::map($entry['data'] ?? null), $data)];
            },
            $this->ttl,
        );
    }

    /** Discard the state and data of a conversation. */
    public function clear(int|string $chatId, int|string|null $userId = null): void
    {
        $this->cache->forget($this->key($chatId, $userId));
    }

    /**
     * State and data of the conversation a message belongs to.
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
     * Move the sender of a message (or of a callback query) into a state.
     *
     * @param array<string, mixed> $data Initial data for the new state
     */
    public function enter(stdClass $payload, string $state, array $data = []): void
    {
        $this->setState(Payload::requireChatId($payload), Payload::userId($payload), $state, $data);
    }

    /**
     * Merge data into the conversation the payload belongs to.
     *
     * @param array<string, mixed> $data Data to add or overwrite
     */
    public function merge(stdClass $payload, array $data): void
    {
        $this->updateData(Payload::requireChatId($payload), Payload::userId($payload), $data);
    }

    /** End the conversation the payload belongs to. */
    public function leave(stdClass $payload): void
    {
        $this->clear(Payload::requireChatId($payload), Payload::userId($payload));
    }

    private function key(int|string $chatId, int|string|null $userId): string
    {
        return $this->prefix . $chatId . ':' . ($userId ?? '*');
    }
}
