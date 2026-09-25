<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Managed bot methods from Telegram Bot API
 *
 * Tokens and access settings of the bots managed by this bot.
 *
 * @see https://core.telegram.org/bots/api#getmanagedbottoken
 */
trait ManagedBotMethods
{
    use CallsApi;

    /**
     * Get the access settings of a managed bot
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getmanagedbotaccesssettings
     */
    public function getManagedBotAccessSettings(int $userId, array $options = []): array
    {
        return $this->apiCallObject('getManagedBotAccessSettings', [
            'user_id' => $userId,
        ], $options);
    }

    /**
     * Get the token of a managed bot
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#getmanagedbottoken
     */
    public function getManagedBotToken(int $userId, array $options = []): string
    {
        return $this->apiCallString('getManagedBotToken', [
            'user_id' => $userId,
        ], $options);
    }

    /**
     * Revoke the current token of a managed bot and generate a new one
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#replacemanagedbottoken
     */
    public function replaceManagedBotToken(int $userId, array $options = []): string
    {
        return $this->apiCallString('replaceManagedBotToken', [
            'user_id' => $userId,
        ], $options);
    }

    /**
     * Change the access settings of a managed bot
     *
     * @param list<int>|null $addedUserIds
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setmanagedbotaccesssettings
     */
    public function setManagedBotAccessSettings(
        int $userId,
        bool $isAccessRestricted,
        ?array $addedUserIds = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setManagedBotAccessSettings', [
            'user_id' => $userId,
            'is_access_restricted' => $isAccessRestricted,
            'added_user_ids' => $addedUserIds,
        ], $options);
    }
}
