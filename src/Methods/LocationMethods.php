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
    use CallsApi;

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options live_period, horizontal_accuracy, heading, proximity_alert_radius...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendlocation
     */
    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options horizontal_accuracy, heading, proximity_alert_radius, inline_message_id...
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagelivelocation
     */
    public function editMessageLiveLocation(
        int|string|null $chatId,
        ?int $messageId,
        float $latitude,
        float $longitude,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
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
        array $options = [],
    ): bool {
        return $this->apiCallBool('stopMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options foursquare_id, google_place_id...
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
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendVenue', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
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
        array $options = [],
    ): array {
        return $this->apiCallObject('sendContact', [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'vcard' => $vcard,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<int, string|array<string, mixed>> $answers Answer texts or InputPollOption objects
     * @param string|null $type "regular" or "quiz"
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options allows_multiple_answers, correct_option_id, explanation, open_period...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendpoll
     */
    public function sendPoll(
        int|string $chatId,
        string $question,
        array $answers,
        ?string $type = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendPoll', [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => array_map(
                static fn(string|array $answer): array => is_string($answer) ? ['text' => $answer] : $answer,
                $answers,
            ),
            'type' => $type,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
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
        array $options = [],
    ): array {
        return $this->apiCallObject('stopPoll', [
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
        array $options = [],
    ): array {
        return $this->apiCallObject('sendDice', [
            'chat_id' => $chatId,
            'emoji' => $emoji,
            'disable_notification' => $disableNotification ? true : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }
}
