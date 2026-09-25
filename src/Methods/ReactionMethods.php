<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Reaction methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#setmessagereaction
 */
trait ReactionMethods
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * Change the bot's reactions on a message
     *
     * Reactions may be ReactionType objects or plain emoji strings
     * (`['👍']`). Pass null or an empty array to remove reactions.
     *
     * @param array<int, string|array<string, mixed>>|null $reaction
     *
     * @see https://core.telegram.org/bots/api#setmessagereaction
     */
    public function setMessageReaction(
        int|string $chatId,
        int $messageId,
        ?array $reaction = null,
        bool $isBig = false
    ): bool {
        $reactions = array_map(
            static fn(string|array $item): array => is_string($item) ? ['type' => 'emoji', 'emoji' => $item] : $item,
            array_values($reaction ?? [])
        );

        return (bool) $this->apiCall('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => $reactions,
            'is_big' => $isBig ?: null,
        ]);
    }
}
