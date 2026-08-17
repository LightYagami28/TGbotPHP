<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Chat methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait ChatMethods
{
    use HttpClientTrait;

    /**
     * Get chat information
     *
     * @see https://core.telegram.org/bots/api#getchat
     */
    public function getChat(int|string $chatId): array|null
    {
        return $this->httpRequest('getChat', [
            'chat_id' => $chatId,
        ], returnResponse: true);
    }

    /**
     * Get chat member
     *
     * @see https://core.telegram.org/bots/api#getchatmember
     */
    public function getChatMember(int|string $chatId, int $userId): array|null
    {
        return $this->httpRequest('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ], returnResponse: true);
    }

    /**
     * Get chat administrators
     *
     * @see https://core.telegram.org/bots/api#getchatadministrators
     */
    public function getChatAdministrators(int|string $chatId): array|null
    {
        return $this->httpRequest('getChatAdministrators', [
            'chat_id' => $chatId,
        ], returnResponse: true);
    }

    /**
     * Get chat members count
     *
     * @see https://core.telegram.org/bots/api#getchatmemberscount
     */
    public function getChatMembersCount(int|string $chatId): int|null
    {
        $result = $this->httpRequest('getChatMembersCount', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null ? (int)$result : null;
    }

    /**
     * Leave chat
     *
     * @see https://core.telegram.org/bots/api#leavechat
     */
    public function leaveChat(int|string $chatId): bool
    {
        $result = $this->httpRequest('leaveChat', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Set chat title
     *
     * @see https://core.telegram.org/bots/api#setchattitle
     */
    public function setChatTitle(int|string $chatId, string $title): bool
    {
        $result = $this->httpRequest('setChatTitle', [
            'chat_id' => $chatId,
            'title' => $title,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Set chat description
     *
     * @see https://core.telegram.org/bots/api#setchatdescription
     */
    public function setChatDescription(int|string $chatId, string $description): bool
    {
        $result = $this->httpRequest('setChatDescription', [
            'chat_id' => $chatId,
            'description' => $description,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Pin message
     *
     * @see https://core.telegram.org/bots/api#pinmessage
     */
    public function pinMessage(
        int|string $chatId,
        int $messageId,
        bool $disableNotification = false
    ): bool {
        $result = $this->httpRequest('pinMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Unpin message
     *
     * @see https://core.telegram.org/bots/api#unpinmessage
     */
    public function unpinMessage(int|string $chatId, int|null $messageId = null): bool
    {
        $result = $this->httpRequest('unpinMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Unpin all messages
     *
     * @see https://core.telegram.org/bots/api#unpinallchatmessages
     */
    public function unpinAllChatMessages(int|string $chatId): bool
    {
        $result = $this->httpRequest('unpinAllChatMessages', [
            'chat_id' => $chatId,
        ], returnResponse: true);

        return $result !== null;
    }
}
