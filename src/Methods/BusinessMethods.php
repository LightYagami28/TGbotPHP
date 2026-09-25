<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Business account methods from Telegram Bot API
 *
 * Manage the accounts of users who connected the bot to their Telegram Business account.
 *
 * @see https://core.telegram.org/bots/api#business-connection
 */
trait BusinessMethods
{
    use CallsApi;

    /**
     * Convert a given regular gift to Telegram Stars
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#convertgifttostars
     */
    public function convertGiftToStars(
        string $businessConnectionId,
        string $ownedGiftId,
        array $options = [],
    ): bool {
        return $this->apiCallBool('convertGiftToStars', [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
        ], $options);
    }

    /**
     * Delete messages on behalf of a business account
     *
     * @param list<int> $messageIds
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#deletebusinessmessages
     */
    public function deleteBusinessMessages(
        string $businessConnectionId,
        array $messageIds,
        array $options = [],
    ): bool {
        return $this->apiCallBool('deleteBusinessMessages', [
            'business_connection_id' => $businessConnectionId,
            'message_ids' => $messageIds,
        ], $options);
    }

    /**
     * Get the gifts received and owned by a managed business account
     *
     * @param array<string, mixed> $options exclude_unsaved, exclude_saved, exclude_unlimited, exclude_limited_upgradable, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getbusinessaccountgifts
     */
    public function getBusinessAccountGifts(string $businessConnectionId, array $options = []): array
    {
        return $this->apiCallObject('getBusinessAccountGifts', [
            'business_connection_id' => $businessConnectionId,
        ], $options);
    }

    /**
     * Get the amount of Telegram Stars owned by a managed business account
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getbusinessaccountstarbalance
     */
    public function getBusinessAccountStarBalance(string $businessConnectionId, array $options = []): array
    {
        return $this->apiCallObject('getBusinessAccountStarBalance', [
            'business_connection_id' => $businessConnectionId,
        ], $options);
    }

    /**
     * Get information about the connection of the bot with a business account
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getbusinessconnection
     */
    public function getBusinessConnection(string $businessConnectionId, array $options = []): array
    {
        return $this->apiCallObject('getBusinessConnection', [
            'business_connection_id' => $businessConnectionId,
        ], $options);
    }

    /**
     * Mark an incoming message as read on behalf of a business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#readbusinessmessage
     */
    public function readBusinessMessage(
        string $businessConnectionId,
        int $chatId,
        int $messageId,
        array $options = [],
    ): bool {
        return $this->apiCallBool('readBusinessMessage', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options);
    }

    /**
     * Remove the current profile photo of a managed business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#removebusinessaccountprofilephoto
     */
    public function removeBusinessAccountProfilePhoto(
        string $businessConnectionId,
        ?bool $isPublic = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('removeBusinessAccountProfilePhoto', [
            'business_connection_id' => $businessConnectionId,
            'is_public' => $isPublic,
        ], $options);
    }

    /**
     * Change the bio of a managed business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setbusinessaccountbio
     */
    public function setBusinessAccountBio(
        string $businessConnectionId,
        ?string $bio = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setBusinessAccountBio', [
            'business_connection_id' => $businessConnectionId,
            'bio' => $bio,
        ], $options);
    }

    /**
     * Change the privacy settings pertaining to incoming gifts in a managed business account
     *
     * @param array<string, mixed> $acceptedGiftTypes
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setbusinessaccountgiftsettings
     */
    public function setBusinessAccountGiftSettings(
        string $businessConnectionId,
        bool $showGiftButton,
        array $acceptedGiftTypes,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setBusinessAccountGiftSettings', [
            'business_connection_id' => $businessConnectionId,
            'show_gift_button' => $showGiftButton,
            'accepted_gift_types' => $acceptedGiftTypes,
        ], $options);
    }

    /**
     * Change the first and last name of a managed business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setbusinessaccountname
     */
    public function setBusinessAccountName(
        string $businessConnectionId,
        string $firstName,
        ?string $lastName = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setBusinessAccountName', [
            'business_connection_id' => $businessConnectionId,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ], $options);
    }

    /**
     * Change the profile photo of a managed business account
     *
     * @param array<string, mixed> $photo
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setbusinessaccountprofilephoto
     */
    public function setBusinessAccountProfilePhoto(
        string $businessConnectionId,
        array $photo,
        ?bool $isPublic = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setBusinessAccountProfilePhoto', [
            'business_connection_id' => $businessConnectionId,
            'photo' => $photo,
            'is_public' => $isPublic,
        ], $options);
    }

    /**
     * Change the username of a managed business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setbusinessaccountusername
     */
    public function setBusinessAccountUsername(
        string $businessConnectionId,
        ?string $username = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setBusinessAccountUsername', [
            'business_connection_id' => $businessConnectionId,
            'username' => $username,
        ], $options);
    }

    /**
     * Transfer Telegram Stars from the business account balance to the bot's balance
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#transferbusinessaccountstars
     */
    public function transferBusinessAccountStars(
        string $businessConnectionId,
        int $starCount,
        array $options = [],
    ): bool {
        return $this->apiCallBool('transferBusinessAccountStars', [
            'business_connection_id' => $businessConnectionId,
            'star_count' => $starCount,
        ], $options);
    }

    /**
     * Transfer an owned unique gift to another user
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#transfergift
     */
    public function transferGift(
        string $businessConnectionId,
        string $ownedGiftId,
        int $newOwnerChatId,
        ?int $starCount = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('transferGift', [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
            'new_owner_chat_id' => $newOwnerChatId,
            'star_count' => $starCount,
        ], $options);
    }

    /**
     * Upgrade a given regular gift to a unique gift
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#upgradegift
     */
    public function upgradeGift(
        string $businessConnectionId,
        string $ownedGiftId,
        ?bool $keepOriginalDetails = null,
        ?int $starCount = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('upgradeGift', [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
            'keep_original_details' => $keepOriginalDetails,
            'star_count' => $starCount,
        ], $options);
    }
}
