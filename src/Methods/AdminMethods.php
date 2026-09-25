<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Chat administration methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait AdminMethods
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
     * @deprecated Use banChatMember()
     */
    public function kickChatMember(
        int|string $chatId,
        int $userId,
        ?int $untilDate = null
    ): bool {
        return $this->banChatMember($chatId, $userId, $untilDate);
    }

    /**
     * Ban chat member
     *
     * @see https://core.telegram.org/bots/api#banchatmember
     */
    public function banChatMember(
        int|string $chatId,
        int $userId,
        ?int $untilDate = null,
        bool $revokeMessages = false
    ): bool {
        return $this->apiCallBool('banChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => $untilDate,
            'revoke_messages' => $revokeMessages ? true : null,
        ]);
    }

    /**
     * Unban chat member
     *
     * @see https://core.telegram.org/bots/api#unbanchatmember
     */
    public function unbanChatMember(
        int|string $chatId,
        int $userId,
        bool $onlyIfBanned = false
    ): bool {
        return $this->apiCallBool('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned ? true : null,
        ]);
    }

    /**
     * Restrict chat member
     *
     * @param array<string, bool> $permissions ChatPermissions object
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#restrictchatmember
     */
    public function restrictChatMember(
        int|string $chatId,
        int $userId,
        array $permissions,
        ?int $untilDate = null,
        array $options = []
    ): bool {
        return $this->apiCallBool('restrictChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => $permissions,
            'until_date' => $untilDate,
        ], $options);
    }

    /**
     * Promote chat member
     *
     * @param array<string, mixed> $options Additional rights (can_post_stories, ...)
     *
     * @see https://core.telegram.org/bots/api#promotechatmember
     */
    public function promoteChatMember(
        int|string $chatId,
        int $userId,
        bool $isAnonymous = false,
        bool $canManageChat = false,
        bool $canDeleteMessages = false,
        bool $canManageVideoChats = false,
        bool $canRestrictMembers = false,
        bool $canPromoteMembers = false,
        bool $canChangeInfo = false,
        bool $canInviteUsers = false,
        bool $canPostMessages = false,
        bool $canEditMessages = false,
        bool $canPinMessages = false,
        bool $canManageTopics = false,
        array $options = []
    ): bool {
        return $this->apiCallBool('promoteChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'is_anonymous' => $isAnonymous,
            'can_manage_chat' => $canManageChat,
            'can_delete_messages' => $canDeleteMessages,
            'can_manage_video_chats' => $canManageVideoChats,
            'can_restrict_members' => $canRestrictMembers,
            'can_promote_members' => $canPromoteMembers,
            'can_change_info' => $canChangeInfo,
            'can_invite_users' => $canInviteUsers,
            'can_post_messages' => $canPostMessages,
            'can_edit_messages' => $canEditMessages,
            'can_pin_messages' => $canPinMessages,
            'can_manage_topics' => $canManageTopics,
        ], $options);
    }

    /**
     * Set chat administrator custom title
     *
     * @see https://core.telegram.org/bots/api#setchatadministratorcustomtitle
     */
    public function setChatAdministratorCustomTitle(
        int|string $chatId,
        int $userId,
        string $customTitle
    ): bool {
        return $this->apiCallBool('setChatAdministratorCustomTitle', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle,
        ]);
    }

    /**
     * Ban a channel chat in a supergroup or channel
     *
     * @see https://core.telegram.org/bots/api#banchatsenderchat
     */
    public function banChatSenderChat(int|string $chatId, int $senderChatId): bool
    {
        return $this->apiCallBool('banChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#unbanchatsenderchat
     */
    public function unbanChatSenderChat(int|string $chatId, int $senderChatId): bool
    {
        return $this->apiCallBool('unbanChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId,
        ]);
    }
}
