<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;
use TGbotPHP\Types\InputFile;

/**
 * Message methods from Telegram Bot API
 *
 * Every method accepts a trailing `$options` array that is merged into the
 * request, so any optional parameter (message_thread_id, reply_parameters,
 * protect_content, business_connection_id, ...) can be passed.
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait MessageMethods
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
     * Send text message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendmessage
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        bool $disableWebPagePreview = false,
        bool $disableNotification = false,
        array $options = []
    ): array {
        return $this->apiCallObject('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'link_preview_options' => $disableWebPagePreview ? ['is_disabled' => true] : null,
            'disable_notification' => $disableNotification ? true : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Forward message
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#forwardmessage
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        bool $disableNotification = false,
        array $options = []
    ): array {
        return $this->apiCallObject('forwardMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification ? true : null,
        ], $options);
    }

    /**
     * Forward multiple messages
     *
     * @param int[] $messageIds
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>> MessageId objects
     *
     * @see https://core.telegram.org/bots/api#forwardmessages
     */
    public function forwardMessages(
        int|string $chatId,
        int|string $fromChatId,
        array $messageIds,
        array $options = []
    ): array {
        return $this->apiCallList('forwardMessages', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => array_values($messageIds),
        ], $options);
    }

    /**
     * Copy message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed> MessageId object
     *
     * @see https://core.telegram.org/bots/api#copymessage
     */
    public function copyMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCallObject('copyMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Copy multiple messages
     *
     * @param int[] $messageIds
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>> MessageId objects
     *
     * @see https://core.telegram.org/bots/api#copymessages
     */
    public function copyMessages(
        int|string $chatId,
        int|string $fromChatId,
        array $messageIds,
        array $options = []
    ): array {
        return $this->apiCallList('copyMessages', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => array_values($messageIds),
        ], $options);
    }

    /**
     * Send photo
     *
     * @param string|InputFile $photo file_id, HTTP URL or InputFile to upload
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendphoto
     */
    public function sendPhoto(
        int|string $chatId,
        string|InputFile $photo,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCallObject('sendPhoto', [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Send audio
     *
     * @param string|InputFile $audio file_id, HTTP URL or InputFile to upload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendaudio
     */
    public function sendAudio(
        int|string $chatId,
        string|InputFile $audio,
        ?string $caption = null,
        ?int $duration = null,
        ?string $performer = null,
        ?string $title = null,
        array $options = []
    ): array {
        return $this->apiCallObject('sendAudio', [
            'chat_id' => $chatId,
            'audio' => $audio,
            'caption' => $caption,
            'duration' => $duration,
            'performer' => $performer,
            'title' => $title,
        ], $options);
    }

    /**
     * Send document
     *
     * @param string|InputFile $document file_id, HTTP URL or InputFile to upload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#senddocument
     */
    public function sendDocument(
        int|string $chatId,
        string|InputFile $document,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array $options = []
    ): array {
        return $this->apiCallObject('sendDocument', [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
        ], $options);
    }

    /**
     * Send video
     *
     * @param string|InputFile $video file_id, HTTP URL or InputFile to upload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendvideo
     */
    public function sendVideo(
        int|string $chatId,
        string|InputFile $video,
        ?string $caption = null,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
        bool $supportsStreaming = false,
        array $options = []
    ): array {
        return $this->apiCallObject('sendVideo', [
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'supports_streaming' => $supportsStreaming ? true : null,
        ], $options);
    }

    /**
     * Edit message text
     *
     * Pass `$chatId = null`, `$messageId = null` and `['inline_message_id' => ...]`
     * in `$options` to edit an inline message.
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool Edited message, or true for inline messages
     *
     * @see https://core.telegram.org/bots/api#editmessagetext
     */
    public function editMessageText(
        int|string|null $chatId,
        ?int $messageId,
        string $text,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit message caption
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagecaption
     */
    public function editMessageCaption(
        int|string|null $chatId,
        ?int $messageId,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit message media
     *
     * @param array<string, mixed> $media InputMedia object; "media" may be an InputFile
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagemedia
     */
    public function editMessageMedia(
        int|string|null $chatId,
        ?int $messageId,
        array $media,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageMedia', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'media' => $media,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit message reply markup
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup null removes the keyboard
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagereplymarkup
     */
    public function editMessageReplyMarkup(
        int|string|null $chatId,
        ?int $messageId,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Delete message
     *
     * @see https://core.telegram.org/bots/api#deletemessage
     */
    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        return $this->apiCallBool('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Delete multiple messages (1-100)
     *
     * @param int[] $messageIds
     *
     * @see https://core.telegram.org/bots/api#deletemessages
     */
    public function deleteMessages(int|string $chatId, array $messageIds): bool
    {
        return $this->apiCallBool('deleteMessages', [
            'chat_id' => $chatId,
            'message_ids' => array_values($messageIds),
        ]);
    }

    /**
     * Send chat action (typing, upload_photo, record_video, ...)
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#sendchataction
     */
    public function sendChatAction(int|string $chatId, string $action, array $options = []): bool
    {
        return $this->apiCallBool('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ], $options);
    }
}
