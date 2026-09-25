<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Concerns;

use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Framework\Kernel;
use TGbotPHP\Session\ConversationManager;

/**
 * Conversation state shortcuts for the Bot
 */
trait ManagesConversations
{
    abstract protected function kernel(): Kernel;

    /**
     * Enable state() handlers, storing the states in $cache
     */
    public function useConversations(CacheInterface $cache, int $ttl = 3600): static
    {
        $this->kernel()->useConversations(new ConversationManager($cache, $ttl));
        return $this;
    }

    public function conversations(): ConversationManager
    {
        return $this->kernel()->conversations()
            ?? throw new \LogicException('Conversations are disabled: call useConversations() first');
    }

    /**
     * Move the sender of a message or callback query into a conversation state
     *
     * @param array<string, mixed> $data
     */
    public function setState(stdClass $payload, string $state, array $data = []): void
    {
        $this->conversations()->enter($payload, $state, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStateData(stdClass $payload, array $data): void
    {
        $this->conversations()->merge($payload, $data);
    }

    public function clearState(stdClass $payload): void
    {
        $this->conversations()->leave($payload);
    }
}
