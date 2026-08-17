<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Forum topic methods from Telegram Bot API
 *
 * Manage topics in forum supergroups.
 * @see https://core.telegram.org/bots/api#forum-topics
 */
trait ForumTopicMethods
{
    use HttpClientTrait;

    /**
     * Create topic in forum supergroup
     *
     * @see https://core.telegram.org/bots/api#createforumtopic
     */
    public function createForumTopic(
        int|string $chatId,
        string $name,
        int|null $iconColor = null,
        string|null $iconCustomEmojiId = null
    ): array|null {
        return $this->httpRequest('createForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
            'icon_color' => $iconColor,
            'icon_custom_emoji_id' => $iconCustomEmojiId,
        ], returnResponse: true);
    }

    /**
     * Edit forum topic
     *
     * @see https://core.telegram.org/bots/api#editforumtopic
     */
    public function editForumTopic(
        int|string $chatId,
        int $messageThreadId,
        string|null $name = null,
        string|null $iconCustomEmojiId = null
    ): bool {
        $result = $this->httpRequest('editForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
            'name' => $name,
            'icon_custom_emoji_id' => $iconCustomEmojiId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Close forum topic
     *
     * @see https://core.telegram.org/bots/api#closeforumtopic
     */
    public function closeForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        $result = $this->httpRequest('closeForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Reopen forum topic
     *
     * @see https://core.telegram.org/bots/api#reopenforumtopic
     */
    public function reopenForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        $result = $this->httpRequest('reopenForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Delete forum topic
     *
     * @see https://core.telegram.org/bots/api#deleteforumtopic
     */
    public function deleteForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        $result = $this->httpRequest('deleteForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Unpin all forum topic messages
     *
     * @see https://core.telegram.org/bots/api#unpinallforumtopicmessages
     */
    public function unpinAllForumTopicMessages(int|string $chatId, int $messageThreadId): bool
    {
        $result = $this->httpRequest('unpinAllForumTopicMessages', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Edit general forum topic
     *
     * @see https://core.telegram.org/bots/api#editgeneralforumtopic
     */
    public function editGeneralForumTopic(int|string $chatId, string $name): bool
    {
        $result = $this->httpRequest('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Close general forum topic
     *
     * @see https://core.telegram.org/bots/api#closegeneralforumtopic
     */
    public function closeGeneralForumTopic(int|string $chatId): bool
    {
        $result = $this->httpRequest('closeGeneralForumTopic', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Reopen general forum topic
     *
     * @see https://core.telegram.org/bots/api#reopengeneralforumtopic
     */
    public function reopenGeneralForumTopic(int|string $chatId): bool
    {
        $result = $this->httpRequest('reopenGeneralForumTopic', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Hide general forum topic
     *
     * @see https://core.telegram.org/bots/api#hidegeneralforumtopic
     */
    public function hideGeneralForumTopic(int|string $chatId): bool
    {
        $result = $this->httpRequest('hideGeneralForumTopic', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Unhide general forum topic
     *
     * @see https://core.telegram.org/bots/api#unhidegeneralforumtopic
     */
    public function unhideGeneralForumTopic(int|string $chatId): bool
    {
        $result = $this->httpRequest('unhideGeneralForumTopic', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }
}
