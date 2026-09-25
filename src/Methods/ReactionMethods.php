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
     * @return array<string, mixed>
     */
    abstract protected function apiCallObject(string $method, array $params = [], array $options = []): array;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    abstract protected function apiCallList(string $method, array $params = [], array $options = []): array;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     */
    abstract protected function apiCallObjectOrTrue(string $method, array $params = [], array $options = []): array|bool;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallBool(string $method, array $params = [], array $options = []): bool;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallInt(string $method, array $params = [], array $options = []): int;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallString(string $method, array $params = [], array $options = []): string;

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

        return $this->apiCallBool('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => $reactions,
            'is_big' => $isBig ? true : null,
        ]);
    }
}
