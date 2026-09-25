<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;

/**
 * Location, venue, contact, poll and dice methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait LocationMethods
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendlocation
     */
    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        ?float $horizontalAccuracy = null,
        ?int $livePeriod = null,
        ?int $heading = null,
        ?int $proximityAlertRadius = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCall('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'horizontal_accuracy' => $horizontalAccuracy,
            'live_period' => $livePeriod,
            'heading' => $heading,
            'proximity_alert_radius' => $proximityAlertRadius,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagelivelocation
     */
    public function editMessageLiveLocation(
        int|string|null $chatId,
        ?int $messageId,
        float $latitude,
        float $longitude,
        ?float $horizontalAccuracy = null,
        ?int $heading = null,
        ?int $proximityAlertRadius = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array|bool {
        return $this->apiCall('editMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'horizontal_accuracy' => $horizontalAccuracy,
            'heading' => $heading,
            'proximity_alert_radius' => $proximityAlertRadius,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#stopmessagelivelocation
     */
    public function stopMessageLiveLocation(
        int|string|null $chatId,
        ?int $messageId,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): bool {
        return (bool) $this->apiCall('stopMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendvenue
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        ?string $foursquareId = null,
        ?string $foursquareType = null,
        ?string $googlePlaceId = null,
        ?string $googlePlaceType = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCall('sendVenue', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
            'foursquare_id' => $foursquareId,
            'foursquare_type' => $foursquareType,
            'google_place_id' => $googlePlaceId,
            'google_place_type' => $googlePlaceType,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendcontact
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        ?string $lastName = null,
        ?string $vcard = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCall('sendContact', [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'vcard' => $vcard,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Send a native poll
     *
     * @param array<int, string|array<string, mixed>> $options Answer options: plain strings or InputPollOption objects
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $extra Additional API parameters
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendpoll
     */
    public function sendPoll(
        int|string $chatId,
        string $question,
        array $options,
        ?string $type = null,
        bool $allowsMultipleAnswers = false,
        ?int $correctOptionId = null,
        ?string $explanation = null,
        ?string $explanationParseMode = 'HTML',
        ?int $openPeriod = null,
        ?int $closeDate = null,
        bool $isClosed = false,
        array|JsonSerializable|null $replyMarkup = null,
        array $extra = []
    ): array {
        $pollOptions = array_map(
            static fn(string|array $option): array => is_string($option) ? ['text' => $option] : $option,
            array_values($options)
        );

        return $this->apiCall('sendPoll', [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => $pollOptions,
            'type' => $type,
            'allows_multiple_answers' => $allowsMultipleAnswers ?: null,
            'correct_option_id' => $correctOptionId,
            'explanation' => $explanation,
            'explanation_parse_mode' => $explanation !== null ? $explanationParseMode : null,
            'open_period' => $openPeriod,
            'close_date' => $closeDate,
            'is_closed' => $isClosed ?: null,
            'reply_markup' => $replyMarkup,
        ], $extra);
    }

    /**
     * Stop a poll
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed> The stopped Poll
     *
     * @see https://core.telegram.org/bots/api#stoppoll
     */
    public function stopPoll(
        int|string $chatId,
        int $messageId,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCall('stopPoll', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#senddice
     */
    public function sendDice(
        int|string $chatId,
        ?string $emoji = null,
        bool $disableNotification = false,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCall('sendDice', [
            'chat_id' => $chatId,
            'emoji' => $emoji,
            'disable_notification' => $disableNotification ?: null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }
}
