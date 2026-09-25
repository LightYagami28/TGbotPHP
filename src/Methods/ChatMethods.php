<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Types\InputFile;

/**
 * Chat methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait ChatMethods
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * Get chat information (ChatFullInfo)
     *
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getchat
     */
    public function getChat(int|string $chatId): array
    {
        return $this->apiCall('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get chat member
     *
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getchatmember
     */
    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->apiCall('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get chat administrators
     *
     * @return array<int, array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getchatadministrators
     */
    public function getChatAdministrators(int|string $chatId): array
    {
        return $this->apiCall('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Get the number of members in a chat
     *
     * @see https://core.telegram.org/bots/api#getchatmembercount
     */
    public function getChatMemberCount(int|string $chatId): int
    {
        return (int) $this->apiCall('getChatMemberCount', ['chat_id' => $chatId]);
    }

    /**
     * @deprecated Use getChatMemberCount()
     */
    public function getChatMembersCount(int|string $chatId): int
    {
        return $this->getChatMemberCount($chatId);
    }

    /**
     * Leave chat
     *
     * @see https://core.telegram.org/bots/api#leavechat
     */
    public function leaveChat(int|string $chatId): bool
    {
        return (bool) $this->apiCall('leaveChat', ['chat_id' => $chatId]);
    }

    /**
     * Set chat title
     *
     * @see https://core.telegram.org/bots/api#setchattitle
     */
    public function setChatTitle(int|string $chatId, string $title): bool
    {
        return (bool) $this->apiCall('setChatTitle', [
            'chat_id' => $chatId,
            'title' => $title,
        ]);
    }

    /**
     * Set chat description
     *
     * @see https://core.telegram.org/bots/api#setchatdescription
     */
    public function setChatDescription(int|string $chatId, string $description): bool
    {
        return (bool) $this->apiCall('setChatDescription', [
            'chat_id' => $chatId,
            'description' => $description,
        ]);
    }

    /**
     * Set chat photo
     *
     * @see https://core.telegram.org/bots/api#setchatphoto
     */
    public function setChatPhoto(int|string $chatId, InputFile $photo): bool
    {
        return (bool) $this->apiCall('setChatPhoto', [
            'chat_id' => $chatId,
            'photo' => $photo,
        ]);
    }

    /**
     * Delete chat photo
     *
     * @see https://core.telegram.org/bots/api#deletechatphoto
     */
    public function deleteChatPhoto(int|string $chatId): bool
    {
        return (bool) $this->apiCall('deleteChatPhoto', ['chat_id' => $chatId]);
    }

    /**
     * Set default chat permissions for all members
     *
     * @param array<string, bool> $permissions ChatPermissions object
     *
     * @see https://core.telegram.org/bots/api#setchatpermissions
     */
    public function setChatPermissions(
        int|string $chatId,
        array $permissions,
        ?bool $useIndependentChatPermissions = null
    ): bool {
        return (bool) $this->apiCall('setChatPermissions', [
            'chat_id' => $chatId,
            'permissions' => $permissions,
            'use_independent_chat_permissions' => $useIndependentChatPermissions,
        ]);
    }

    /**
     * Pin a message
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#pinchatmessage
     */
    public function pinChatMessage(
        int|string $chatId,
        int $messageId,
        bool $disableNotification = false,
        array $options = []
    ): bool {
        return (bool) $this->apiCall('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification ?: null,
        ], $options);
    }

    /**
     * @deprecated Use pinChatMessage()
     */
    public function pinMessage(int|string $chatId, int $messageId, bool $disableNotification = false): bool
    {
        return $this->pinChatMessage($chatId, $messageId, $disableNotification);
    }

    /**
     * Unpin a message (the most recent pinned one when $messageId is null)
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#unpinchatmessage
     */
    public function unpinChatMessage(int|string $chatId, ?int $messageId = null, array $options = []): bool
    {
        return (bool) $this->apiCall('unpinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options);
    }

    /**
     * @deprecated Use unpinChatMessage()
     */
    public function unpinMessage(int|string $chatId, ?int $messageId = null): bool
    {
        return $this->unpinChatMessage($chatId, $messageId);
    }

    /**
     * Unpin all messages
     *
     * @see https://core.telegram.org/bots/api#unpinallchatmessages
     */
    public function unpinAllChatMessages(int|string $chatId): bool
    {
        return (bool) $this->apiCall('unpinAllChatMessages', ['chat_id' => $chatId]);
    }

    /**
     * Generate a new primary invite link
     *
     * @see https://core.telegram.org/bots/api#exportchatinvitelink
     */
    public function exportChatInviteLink(int|string $chatId): string
    {
        return (string) $this->apiCall('exportChatInviteLink', ['chat_id' => $chatId]);
    }

    /**
     * Create an additional invite link
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed> ChatInviteLink
     *
     * @see https://core.telegram.org/bots/api#createchatinvitelink
     */
    public function createChatInviteLink(
        int|string $chatId,
        ?string $name = null,
        ?int $expireDate = null,
        ?int $memberLimit = null,
        bool $createsJoinRequest = false,
        array $options = []
    ): array {
        return $this->apiCall('createChatInviteLink', [
            'chat_id' => $chatId,
            'name' => $name,
            'expire_date' => $expireDate,
            'member_limit' => $memberLimit,
            'creates_join_request' => $createsJoinRequest ?: null,
        ], $options);
    }

    /**
     * Edit a non-primary invite link
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed> ChatInviteLink
     *
     * @see https://core.telegram.org/bots/api#editchatinvitelink
     */
    public function editChatInviteLink(int|string $chatId, string $inviteLink, array $options = []): array
    {
        return $this->apiCall('editChatInviteLink', [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink,
        ], $options);
    }

    /**
     * Revoke an invite link
     *
     * @return array<string, mixed> ChatInviteLink
     *
     * @see https://core.telegram.org/bots/api#revokechatinvitelink
     */
    public function revokeChatInviteLink(int|string $chatId, string $inviteLink): array
    {
        return $this->apiCall('revokeChatInviteLink', [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#approvechatjoinrequest
     */
    public function approveChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return (bool) $this->apiCall('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#declinechatjoinrequest
     */
    public function declineChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return (bool) $this->apiCall('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatstickerset
     */
    public function setChatStickerSet(int|string $chatId, string $stickerSetName): bool
    {
        return (bool) $this->apiCall('setChatStickerSet', [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletechatstickerset
     */
    public function deleteChatStickerSet(int|string $chatId): bool
    {
        return (bool) $this->apiCall('deleteChatStickerSet', ['chat_id' => $chatId]);
    }
}
