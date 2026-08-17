<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Chat administration methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait AdminMethods
{
    use HttpClientTrait;

    /**
     * Kick chat member
     *
     * @see https://core.telegram.org/bots/api#kickchatmember
     */
    public function kickChatMember(
        int|string $chatId,
        int $userId,
        int|null $untilDate = null
    ): bool {
        $result = $this->httpRequest('kickChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => $untilDate,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Ban chat member
     *
     * @see https://core.telegram.org/bots/api#banchatmember
     */
    public function banChatMember(
        int|string $chatId,
        int $userId,
        int|null $untilDate = null,
        bool $revokeMessages = false
    ): bool {
        $result = $this->httpRequest('banChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => $untilDate,
            'revoke_messages' => $revokeMessages ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
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
        $result = $this->httpRequest('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Restrict chat member
     *
     * @see https://core.telegram.org/bots/api#restrictchatmember
     */
    public function restrictChatMember(
        int|string $chatId,
        int $userId,
        array $permissions,
        int|null $untilDate = null
    ): bool {
        $result = $this->httpRequest('restrictChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions),
            'until_date' => $untilDate,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Promote chat member
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
        bool $canManageTopics = false
    ): bool {
        $result = $this->httpRequest('promoteChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'is_anonymous' => $isAnonymous ? 'true' : 'false',
            'can_manage_chat' => $canManageChat ? 'true' : 'false',
            'can_delete_messages' => $canDeleteMessages ? 'true' : 'false',
            'can_manage_video_chats' => $canManageVideoChats ? 'true' : 'false',
            'can_restrict_members' => $canRestrictMembers ? 'true' : 'false',
            'can_promote_members' => $canPromoteMembers ? 'true' : 'false',
            'can_change_info' => $canChangeInfo ? 'true' : 'false',
            'can_invite_users' => $canInviteUsers ? 'true' : 'false',
            'can_post_messages' => $canPostMessages ? 'true' : 'false',
            'can_edit_messages' => $canEditMessages ? 'true' : 'false',
            'can_pin_messages' => $canPinMessages ? 'true' : 'false',
            'can_manage_topics' => $canManageTopics ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
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
        $result = $this->httpRequest('setChatAdministratorCustomTitle', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle,
        ], returnResponse: true);

        return $result !== null;
    }
}
