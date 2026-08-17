<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

trait LocationMethods
{
    use HttpClientTrait;

    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        float|null $horizontalAccuracy = null,
        int|null $livePeriod = null,
        int|null $heading = null,
        int|null $proximityAlertRadius = null,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'horizontal_accuracy' => $horizontalAccuracy,
            'live_period' => $livePeriod,
            'heading' => $heading,
            'proximity_alert_radius' => $proximityAlertRadius,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function editMessageLiveLocation(
        int|string $chatId,
        int $messageId,
        float $latitude,
        float $longitude,
        float|null $horizontalAccuracy = null,
        int|null $heading = null,
        int|null $proximityAlertRadius = null,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('editMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'horizontal_accuracy' => $horizontalAccuracy,
            'heading' => $heading,
            'proximity_alert_radius' => $proximityAlertRadius,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function stopMessageLiveLocation(
        int|string $chatId,
        int $messageId,
        array|null $replyMarkup = null
    ): bool {
        $result = $this->httpRequest('stopMessageLiveLocation', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);

        return $result !== null;
    }

    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        string|null $foursquareId = null,
        string|null $foursquareType = null,
        string|null $googlePlaceId = null,
        string|null $googlePlaceType = null,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendVenue', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
            'foursquare_id' => $foursquareId,
            'foursquare_type' => $foursquareType,
            'google_place_id' => $googlePlaceId,
            'google_place_type' => $googlePlaceType,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        string|null $lastName = null,
        string|null $vcard = null,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendContact', [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'vcard' => $vcard,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendPoll(
        int|string $chatId,
        string $question,
        array $options,
        string|null $type = null,
        bool $allowsMultipleAnswers = false,
        int|null $correctOptionId = null,
        string|null $explanation = null,
        string $explanationParseMode = 'HTML',
        int|null $openPeriod = null,
        int|null $closeDate = null,
        bool $isClosed = false,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendPoll', [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($options),
            'type' => $type,
            'allows_multiple_answers' => $allowsMultipleAnswers ? 'true' : 'false',
            'correct_option_id' => $correctOptionId,
            'explanation' => $explanation,
            'explanation_parse_mode' => $explanationParseMode,
            'open_period' => $openPeriod,
            'close_date' => $closeDate,
            'is_closed' => $isClosed ? 'true' : 'false',
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendDice(
        int|string $chatId,
        string|null $emoji = null,
        bool $disableNotification = false,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendDice', [
            'chat_id' => $chatId,
            'emoji' => $emoji,
            'disable_notification' => $disableNotification ? 'true' : 'false',
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }
}
