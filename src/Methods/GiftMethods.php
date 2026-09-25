<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Gift methods from Telegram Bot API
 *
 * Send gifts and Telegram Premium subscriptions, and list owned gifts.
 *
 * @see https://core.telegram.org/bots/api#sendgift
 */
trait GiftMethods
{
    use CallsApi;

    /**
     * Get the gifts that can be sent by the bot to users and channel chats
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getavailablegifts
     */
    public function getAvailableGifts(array $options = []): array
    {
        return $this->apiCallObject('getAvailableGifts', [], $options);
    }

    /**
     * Get the gifts owned by a chat
     *
     * @param array<string, mixed> $options exclude_unsaved, exclude_saved, exclude_unlimited, exclude_limited_upgradable, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getchatgifts
     */
    public function getChatGifts(int|string $chatId, array $options = []): array
    {
        return $this->apiCallObject('getChatGifts', [
            'chat_id' => $chatId,
        ], $options);
    }

    /**
     * Get the gifts owned and hosted by a user
     *
     * @param array<string, mixed> $options exclude_unlimited, exclude_limited_upgradable, exclude_limited_non_upgradable, exclude_from_blockchain, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getusergifts
     */
    public function getUserGifts(int $userId, array $options = []): array
    {
        return $this->apiCallObject('getUserGifts', [
            'user_id' => $userId,
        ], $options);
    }

    /**
     * Gift a Telegram Premium subscription to the given user
     *
     * @param array<string, mixed> $options text, text_parse_mode, text_entities
     *
     * @see https://core.telegram.org/bots/api#giftpremiumsubscription
     */
    public function giftPremiumSubscription(
        int $userId,
        int $monthCount,
        int $starCount,
        array $options = [],
    ): bool {
        return $this->apiCallBool('giftPremiumSubscription', [
            'user_id' => $userId,
            'month_count' => $monthCount,
            'star_count' => $starCount,
        ], $options);
    }

    /**
     * Send a gift to the given user or channel chat
     *
     * @param array<string, mixed> $options pay_for_upgrade, text_parse_mode, text_entities
     *
     * @see https://core.telegram.org/bots/api#sendgift
     */
    public function sendGift(
        string $giftId,
        ?int $userId = null,
        int|string|null $chatId = null,
        ?string $text = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('sendGift', [
            'gift_id' => $giftId,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'text' => $text,
        ], $options);
    }
}
