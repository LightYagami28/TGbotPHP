<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use CURLFile;
use TGbotPHP\Traits\HttpClientTrait;

trait MediaMethods
{
    use HttpClientTrait;

    public function sendAnimation(
        int|string $chatId,
        string $animation,
        int|null $duration = null,
        int|null $width = null,
        int|null $height = null,
        string|null $thumbnail = null,
        string|null $caption = null,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendAnimation', [
            'chat_id' => $chatId,
            'animation' => new CURLFile($animation),
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumbnail' => $thumbnail,
            'caption' => $caption,
            'parse_mode' => $parseMode,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendVoice(
        int|string $chatId,
        string $voice,
        string|null $caption = null,
        int|null $duration = null,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendVoice', [
            'chat_id' => $chatId,
            'voice' => new CURLFile($voice),
            'caption' => $caption,
            'duration' => $duration,
            'parse_mode' => $parseMode,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendVideoNote(
        int|string $chatId,
        string $videoNote,
        int|null $duration = null,
        int|null $length = null,
        string|null $thumbnail = null,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendVideoNote', [
            'chat_id' => $chatId,
            'video_note' => new CURLFile($videoNote),
            'duration' => $duration,
            'length' => $length,
            'thumbnail' => $thumbnail,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    public function sendMediaGroup(
        int|string $chatId,
        array $media,
        bool $disableNotification = false
    ): array|null {
        return $this->httpRequest('sendMediaGroup', [
            'chat_id' => $chatId,
            'media' => json_encode($media),
            'disable_notification' => $disableNotification ? 'true' : 'false',
        ], returnResponse: true);
    }
}
