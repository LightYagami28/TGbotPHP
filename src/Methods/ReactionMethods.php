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
    use CallsApi;

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
        bool $isBig = false,
    ): bool {
        $reactions = array_map(
            static fn(string|array $item): array => is_string($item) ? ['type' => 'emoji', 'emoji' => $item] : $item,
            array_values($reaction ?? []),
        );

        return $this->apiCallBool('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => $reactions,
            'is_big' => $isBig ? true : null,
        ]);
    }

    /**
     * Remove up to 10000 recent reactions in a group or a supergroup chat added by a given user or chat
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#deleteallmessagereactions
     */
    public function deleteAllMessageReactions(
        int|string $chatId,
        ?int $userId = null,
        ?int $actorChatId = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('deleteAllMessageReactions', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'actor_chat_id' => $actorChatId,
        ], $options);
    }

    /**
     * Remove a reaction from a message in a group or a supergroup chat
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#deletemessagereaction
     */
    public function deleteMessageReaction(
        int|string $chatId,
        int $messageId,
        ?int $userId = null,
        ?int $actorChatId = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('deleteMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'actor_chat_id' => $actorChatId,
        ], $options);
    }
}
