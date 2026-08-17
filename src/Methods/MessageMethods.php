<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use CURLFile;
use TGbotPHP\Traits\HttpClientTrait;

/**
 * Message methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait MessageMethods
{
    use HttpClientTrait;

    /**
     * Send text message
     *
     * @see https://core.telegram.org/bots/api#sendmessage
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null,
        bool $disableWebPagePreview = false,
        bool $disableNotification = false
    ): array|null {
        return $this->httpRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disableWebPagePreview ? 'true' : 'false',
            'disable_notification' => $disableNotification ? 'true' : 'false',
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    /**
     * Forward message
     *
     * @see https://core.telegram.org/bots/api#forwardmessage
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        bool $disableNotification = false
    ): array|null {
        return $this->httpRequest('forwardMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification ? 'true' : 'false',
        ], returnResponse: true);
    }

    /**
     * Copy message
     *
     * @see https://core.telegram.org/bots/api#copymessage
     */
    public function copyMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        string|null $caption = null,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('copyMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => $parseMode,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    /**
     * Send photo
     *
     * @see https://core.telegram.org/bots/api#sendphoto
     */
    public function sendPhoto(
        int|string $chatId,
        string $photo,
        string|null $caption = null,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null
    ): array|null {
        $data = [
            'chat_id' => $chatId,
            'photo' => new CURLFile($photo),
            'caption' => $caption,
            'parse_mode' => $parseMode,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ];

        return $this->httpRequest('sendPhoto', $data, returnResponse: true);
    }

    /**
     * Send audio
     *
     * @see https://core.telegram.org/bots/api#sendaudio
     */
    public function sendAudio(
        int|string $chatId,
        string $audio,
        string|null $caption = null,
        int|null $duration = null,
        string|null $performer = null,
        string|null $title = null
    ): array|null {
        return $this->httpRequest('sendAudio', [
            'chat_id' => $chatId,
            'audio' => new CURLFile($audio),
            'caption' => $caption,
            'duration' => $duration,
            'performer' => $performer,
            'title' => $title,
        ], returnResponse: true);
    }

    /**
     * Send document
     *
     * @see https://core.telegram.org/bots/api#senddocument
     */
    public function sendDocument(
        int|string $chatId,
        string $document,
        string|null $caption = null,
        string $parseMode = 'HTML'
    ): array|null {
        return $this->httpRequest('sendDocument', [
            'chat_id' => $chatId,
            'document' => new CURLFile($document),
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ], returnResponse: true);
    }

    /**
     * Send video
     *
     * @see https://core.telegram.org/bots/api#sendvideo
     */
    public function sendVideo(
        int|string $chatId,
        string $video,
        string|null $caption = null,
        int|null $duration = null,
        int|null $width = null,
        int|null $height = null,
        bool $supportsStreaming = false
    ): array|null {
        return $this->httpRequest('sendVideo', [
            'chat_id' => $chatId,
            'video' => new CURLFile($video),
            'caption' => $caption,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'supports_streaming' => $supportsStreaming ? 'true' : 'false',
        ], returnResponse: true);
    }

    /**
     * Edit message text
     *
     * @see https://core.telegram.org/bots/api#editmessagetext
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        string $parseMode = 'HTML',
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    /**
     * Delete message
     *
     * @see https://core.telegram.org/bots/api#deletemessage
     */
    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        $result = $this->httpRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Send chat action
     *
     * @see https://core.telegram.org/bots/api#sendchataction
     */
    public function sendChatAction(int|string $chatId, string $action): bool
    {
        $result = $this->httpRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ], returnResponse: true);

        return $result !== null;
    }
}
