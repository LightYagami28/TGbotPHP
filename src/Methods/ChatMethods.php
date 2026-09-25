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
     * Get chat information (ChatFullInfo)
     *
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getchat
     */
    public function getChat(int|string $chatId): array
    {
        return $this->apiCallObject('getChat', ['chat_id' => $chatId]);
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
        return $this->apiCallObject('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get chat administrators
     *
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getchatadministrators
     */
    public function getChatAdministrators(int|string $chatId): array
    {
        return $this->apiCallList('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Get the number of members in a chat
     *
     * @see https://core.telegram.org/bots/api#getchatmembercount
     */
    public function getChatMemberCount(int|string $chatId): int
    {
        return $this->apiCallInt('getChatMemberCount', ['chat_id' => $chatId]);
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
        return $this->apiCallBool('leaveChat', ['chat_id' => $chatId]);
    }

    /**
     * Set chat title
     *
     * @see https://core.telegram.org/bots/api#setchattitle
     */
    public function setChatTitle(int|string $chatId, string $title): bool
    {
        return $this->apiCallBool('setChatTitle', [
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
        return $this->apiCallBool('setChatDescription', [
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
        return $this->apiCallBool('setChatPhoto', [
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
        return $this->apiCallBool('deleteChatPhoto', ['chat_id' => $chatId]);
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
        return $this->apiCallBool('setChatPermissions', [
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
        return $this->apiCallBool('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification ? true : null,
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
        return $this->apiCallBool('unpinChatMessage', [
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
        return $this->apiCallBool('unpinAllChatMessages', ['chat_id' => $chatId]);
    }

    /**
     * Generate a new primary invite link
     *
     * @see https://core.telegram.org/bots/api#exportchatinvitelink
     */
    public function exportChatInviteLink(int|string $chatId): string
    {
        return $this->apiCallString('exportChatInviteLink', ['chat_id' => $chatId]);
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
        return $this->apiCallObject('createChatInviteLink', [
            'chat_id' => $chatId,
            'name' => $name,
            'expire_date' => $expireDate,
            'member_limit' => $memberLimit,
            'creates_join_request' => $createsJoinRequest ? true : null,
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
        return $this->apiCallObject('editChatInviteLink', [
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
        return $this->apiCallObject('revokeChatInviteLink', [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#approvechatjoinrequest
     */
    public function approveChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return $this->apiCallBool('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#declinechatjoinrequest
     */
    public function declineChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return $this->apiCallBool('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatstickerset
     */
    public function setChatStickerSet(int|string $chatId, string $stickerSetName): bool
    {
        return $this->apiCallBool('setChatStickerSet', [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletechatstickerset
     */
    public function deleteChatStickerSet(int|string $chatId): bool
    {
        return $this->apiCallBool('deleteChatStickerSet', ['chat_id' => $chatId]);
    }
}
