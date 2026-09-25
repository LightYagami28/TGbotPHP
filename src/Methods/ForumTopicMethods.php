<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Forum topic methods from Telegram Bot API
 *
 * Manage topics in forum supergroups.
 * @see https://core.telegram.org/bots/api#createforumtopic
 */
trait ForumTopicMethods
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
     * Get custom emoji stickers usable as forum topic icons
     *
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getforumtopiciconstickers
     */
    public function getForumTopicIconStickers(): array
    {
        return $this->apiCallList('getForumTopicIconStickers');
    }

    /**
     * Create topic in forum supergroup
     *
     * @return array<string, mixed> ForumTopic
     *
     * @see https://core.telegram.org/bots/api#createforumtopic
     */
    public function createForumTopic(
        int|string $chatId,
        string $name,
        ?int $iconColor = null,
        ?string $iconCustomEmojiId = null,
    ): array {
        return $this->apiCallObject('createForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
            'icon_color' => $iconColor,
            'icon_custom_emoji_id' => $iconCustomEmojiId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#editforumtopic
     */
    public function editForumTopic(
        int|string $chatId,
        int $messageThreadId,
        ?string $name = null,
        ?string $iconCustomEmojiId = null,
    ): bool {
        return $this->apiCallBool('editForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
            'name' => $name,
            'icon_custom_emoji_id' => $iconCustomEmojiId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#closeforumtopic
     */
    public function closeForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return $this->forumTopicAction('closeForumTopic', $chatId, $messageThreadId);
    }

    /**
     * @see https://core.telegram.org/bots/api#reopenforumtopic
     */
    public function reopenForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return $this->forumTopicAction('reopenForumTopic', $chatId, $messageThreadId);
    }

    /**
     * @see https://core.telegram.org/bots/api#deleteforumtopic
     */
    public function deleteForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return $this->forumTopicAction('deleteForumTopic', $chatId, $messageThreadId);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallforumtopicmessages
     */
    public function unpinAllForumTopicMessages(int|string $chatId, int $messageThreadId): bool
    {
        return $this->forumTopicAction('unpinAllForumTopicMessages', $chatId, $messageThreadId);
    }

    /**
     * @see https://core.telegram.org/bots/api#editgeneralforumtopic
     */
    public function editGeneralForumTopic(int|string $chatId, string $name): bool
    {
        return $this->apiCallBool('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#closegeneralforumtopic
     */
    public function closeGeneralForumTopic(int|string $chatId): bool
    {
        return $this->apiCallBool('closeGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#reopengeneralforumtopic
     */
    public function reopenGeneralForumTopic(int|string $chatId): bool
    {
        return $this->apiCallBool('reopenGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#hidegeneralforumtopic
     */
    public function hideGeneralForumTopic(int|string $chatId): bool
    {
        return $this->apiCallBool('hideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#unhidegeneralforumtopic
     */
    public function unhideGeneralForumTopic(int|string $chatId): bool
    {
        return $this->apiCallBool('unhideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallgeneralforumtopicmessages
     */
    public function unpinAllGeneralForumTopicMessages(int|string $chatId): bool
    {
        return $this->apiCallBool('unpinAllGeneralForumTopicMessages', ['chat_id' => $chatId]);
    }

    private function forumTopicAction(string $method, int|string $chatId, int $messageThreadId): bool
    {
        return $this->apiCallBool($method, [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }
}
