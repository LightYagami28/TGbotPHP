<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Verification methods from Telegram Bot API.
 *
 * For bots of organizations that verify users and chats on behalf of Telegram.
 *
 * @see https://core.telegram.org/bots/api#verifyuser
 */
trait VerificationMethods
{
    use CallsApi;

    /**
     * Remove verification from a chat verified on behalf of the organization.
     *
     * @param array<string, mixed> $options Extra parameters overriding the named ones
     *
     * @see https://core.telegram.org/bots/api#removechatverification
     */
    public function removeChatVerification(int|string $chatId, array $options = []): bool
    {
        return $this->apiCallBool('removeChatVerification', [
            'chat_id' => $chatId,
        ], $options);
    }

    /**
     * Remove verification from a user verified on behalf of the organization.
     *
     * @param array<string, mixed> $options Extra parameters overriding the named ones
     *
     * @see https://core.telegram.org/bots/api#removeuserverification
     */
    public function removeUserVerification(int $userId, array $options = []): bool
    {
        return $this->apiCallBool('removeUserVerification', [
            'user_id' => $userId,
        ], $options);
    }

    /**
     * Verify a chat on behalf of the organization represented by the bot.
     *
     * @param array<string, mixed> $options Extra parameters overriding the named ones
     *
     * @see https://core.telegram.org/bots/api#verifychat
     */
    public function verifyChat(
        int|string $chatId,
        ?string $customDescription = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('verifyChat', [
            'chat_id' => $chatId,
            'custom_description' => $customDescription,
        ], $options);
    }

    /**
     * Verify a user on behalf of the organization represented by the bot.
     *
     * @param array<string, mixed> $options Extra parameters overriding the named ones
     *
     * @see https://core.telegram.org/bots/api#verifyuser
     */
    public function verifyUser(
        int $userId,
        ?string $customDescription = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('verifyUser', [
            'user_id' => $userId,
            'custom_description' => $customDescription,
        ], $options);
    }
}
