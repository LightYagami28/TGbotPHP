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
     * Send animation (GIF or H.264/MPEG-4 AVC video without sound)
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendanimation
     */
    public function sendAnimation(
        int|string $chatId,
        string|InputFile $animation,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
        string|InputFile|null $thumbnail = null,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCallObject('sendAnimation', [
            'chat_id' => $chatId,
            'animation' => $animation,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumbnail' => $thumbnail,
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
        array $options = []
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
        array $options = []
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
        array $options = []
    ): array {
        return $this->apiCallList('sendMediaGroup', [
            'chat_id' => $chatId,
            'media' => array_values($media),
            'disable_notification' => $disableNotification ? true : null,
        ], $options);
    }
}
