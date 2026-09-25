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
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * Get custom emoji stickers usable as forum topic icons
     *
     * @return array<int, array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getforumtopiciconstickers
     */
    public function getForumTopicIconStickers(): array
    {
        return $this->apiCall('getForumTopicIconStickers');
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
        ?string $iconCustomEmojiId = null
    ): array {
        return $this->apiCall('createForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
            'icon_color' => $iconColor,
            'icon_custom_emoji_id' => $iconCustomEmojiId,
        ]);
    }

    /**
     * Edit forum topic
     *
     * @see https://core.telegram.org/bots/api#editforumtopic
     */
    public function editForumTopic(
        int|string $chatId,
        int $messageThreadId,
        ?string $name = null,
        ?string $iconCustomEmojiId = null
    ): bool {
        return (bool) $this->apiCall('editForumTopic', [
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
        return (bool) $this->apiCall('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#closegeneralforumtopic
     */
    public function closeGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->apiCall('closeGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#reopengeneralforumtopic
     */
    public function reopenGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->apiCall('reopenGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#hidegeneralforumtopic
     */
    public function hideGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->apiCall('hideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#unhidegeneralforumtopic
     */
    public function unhideGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->apiCall('unhideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallgeneralforumtopicmessages
     */
    public function unpinAllGeneralForumTopicMessages(int|string $chatId): bool
    {
        return (bool) $this->apiCall('unpinAllGeneralForumTopicMessages', ['chat_id' => $chatId]);
    }

    private function forumTopicAction(string $method, int|string $chatId, int $messageThreadId): bool
    {
        return (bool) $this->apiCall($method, [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }
}
