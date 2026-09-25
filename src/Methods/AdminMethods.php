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
     * @param array<string, bool> $rights ChatAdministratorRights, e.g. ['can_delete_messages' => true]
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#promotechatmember
     */
    public function promoteChatMember(int|string $chatId, int $userId, array $rights = [], array $options = []): bool
    {
        return $this->apiCallBool('promoteChatMember', ['chat_id' => $chatId, 'user_id' => $userId] + $rights, $options);
    }

    /**
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
