<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;
use TGbotPHP\Types\InputFile;

/**
 * Media methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait MediaMethods
{
    use CallsApi;

    /**
     * Send a GIF or an H.264/MPEG-4 AVC video without sound
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options duration, width, height, thumbnail...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendanimation
     */
    public function sendAnimation(
        int|string $chatId,
        string|InputFile $animation,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendAnimation', [
            'chat_id' => $chatId,
            'animation' => $animation,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Send voice message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendvoice
     */
    public function sendVoice(
        int|string $chatId,
        string|InputFile $voice,
        ?string $caption = null,
        ?int $duration = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendVoice', [
            'chat_id' => $chatId,
            'voice' => $voice,
            'caption' => $caption,
            'duration' => $duration,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Send video note (rounded square video)
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendvideonote
     */
    public function sendVideoNote(
        int|string $chatId,
        string|InputFile $videoNote,
        ?int $duration = null,
        ?int $length = null,
        string|InputFile|null $thumbnail = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendVideoNote', [
            'chat_id' => $chatId,
            'video_note' => $videoNote,
            'duration' => $duration,
            'length' => $length,
            'thumbnail' => $thumbnail,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Send a group of photos, videos, documents or audios as an album
     *
     * Each InputMedia "media" field may be a file_id, a URL or an InputFile.
     *
     * @param array<int, array<string, mixed>> $media
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#sendmediagroup
     */
    public function sendMediaGroup(
        int|string $chatId,
        array $media,
        bool $disableNotification = false,
        array $options = [],
    ): array {
        return $this->apiCallList('sendMediaGroup', [
            'chat_id' => $chatId,
            'media' => array_values($media),
            'disable_notification' => $disableNotification ? true : null,
        ], $options);
    }

    /**
     * Send a live photo
     *
     * @param array<string, mixed> $options business_connection_id, message_thread_id, direct_messages_topic_id, ephemeral_message_parameters, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendlivephoto
     */
    public function sendLivePhoto(
        int|string $chatId,
        InputFile|string $livePhoto,
        InputFile|string $photo,
        ?string $caption = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendLivePhoto', [
            'chat_id' => $chatId,
            'live_photo' => $livePhoto,
            'photo' => $photo,
            'caption' => $caption,
        ], $options);
    }

    /**
     * Send paid media
     *
     * @param list<array<string, mixed>> $media
     * @param array<string, mixed> $options business_connection_id, message_thread_id, direct_messages_topic_id, payload, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendpaidmedia
     */
    public function sendPaidMedia(
        int|string $chatId,
        int $starCount,
        array $media,
        ?string $caption = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendPaidMedia', [
            'chat_id' => $chatId,
            'star_count' => $starCount,
            'media' => $media,
            'caption' => $caption,
        ], $options);
    }
}
