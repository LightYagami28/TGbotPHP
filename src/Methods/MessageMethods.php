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
    use CallsApi;

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
        array $options = [],
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
        array $options = [],
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
        array $options = [],
    ): array {
        return $this->apiCallList('forwardMessages', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => array_values($messageIds),
        ], $options);
    }

    /**
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
        array $options = [],
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
        array $options = [],
    ): array {
        return $this->apiCallList('copyMessages', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => array_values($messageIds),
        ], $options);
    }

    /**
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
        array $options = [],
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
        array $options = [],
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
        array $options = [],
    ): array {
        return $this->apiCallObject('sendDocument', [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
        ], $options);
    }

    /**
     * @param string|InputFile $video file_id, HTTP URL or InputFile to upload
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options duration, width, height, supports_streaming, thumbnail...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendvideo
     */
    public function sendVideo(
        int|string $chatId,
        string|InputFile $video,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendVideo', [
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? $parseMode : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Pass `$chatId = null`, `$messageId = null` and `['inline_message_id' => ...]`
     * in `$options` to edit an inline message.
     *
     * Telegram removes the inline keyboard when $replyMarkup is omitted: pass
     * it again to keep the buttons.
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
        array $options = [],
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
        array $options = [],
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
        array $options = [],
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageMedia', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'media' => $media,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup New inline keyboard; null removes it
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @see https://core.telegram.org/bots/api#editmessagereplymarkup
     */
    public function editMessageReplyMarkup(
        int|string|null $chatId,
        ?int $messageId,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array|bool {
        return $this->apiCallObjectOrTrue('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            // An empty keyboard is the explicit way to remove it
            'reply_markup' => $replyMarkup ?? ['inline_keyboard' => []],
        ], $options);
    }

    /**
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

    /**
     * Approve a suggested post in a direct messages chat
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#approvesuggestedpost
     */
    public function approveSuggestedPost(
        int $chatId,
        int $messageId,
        ?int $sendDate = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('approveSuggestedPost', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'send_date' => $sendDate,
        ], $options);
    }

    /**
     * Decline a suggested post in a direct messages chat
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#declinesuggestedpost
     */
    public function declineSuggestedPost(
        int $chatId,
        int $messageId,
        ?string $comment = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('declineSuggestedPost', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'comment' => $comment,
        ], $options);
    }

    /**
     * Edit a checklist on behalf of a connected business account
     *
     * @param array<string, mixed> $checklist
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#editmessagechecklist
     */
    public function editMessageChecklist(
        string $businessConnectionId,
        int|string $chatId,
        int $messageId,
        array $checklist,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('editMessageChecklist', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'checklist' => $checklist,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Get the last messages from the personal chat a user added to their profile
     *
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getuserpersonalchatmessages
     */
    public function getUserPersonalChatMessages(
        int $userId,
        int $limit,
        array $options = [],
    ): array {
        return $this->apiCallList('getUserPersonalChatMessages', [
            'user_id' => $userId,
            'limit' => $limit,
        ], $options);
    }

    /**
     * Send a checklist on behalf of a connected business account
     *
     * @param array<string, mixed> $checklist
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options disable_notification, protect_content, message_effect_id, reply_parameters
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendchecklist
     */
    public function sendChecklist(
        string $businessConnectionId,
        int|string $chatId,
        array $checklist,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendChecklist', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'checklist' => $checklist,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Stream a partial message to a user while the message is being generated
     *
     * @param array<string, mixed> $options message_thread_id, parse_mode, entities, can_stop, ...
     *
     * @see https://core.telegram.org/bots/api#sendmessagedraft
     */
    public function sendMessageDraft(
        int $chatId,
        int $draftId,
        ?string $text = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('sendMessageDraft', [
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'text' => $text,
        ], $options);
    }

    /**
     * Send a rich message
     *
     * @param array<string, mixed> $richMessage
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options business_connection_id, message_thread_id, direct_messages_topic_id, ephemeral_message_parameters, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendrichmessage
     */
    public function sendRichMessage(
        int|string $chatId,
        array $richMessage,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendRichMessage', [
            'chat_id' => $chatId,
            'rich_message' => $richMessage,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Stream a partial rich message to a user while the message is being generated
     *
     * @param array<string, mixed> $richMessage
     * @param array<string, mixed> $options message_thread_id, can_stop, keep_on_stop
     *
     * @see https://core.telegram.org/bots/api#sendrichmessagedraft
     */
    public function sendRichMessageDraft(
        int $chatId,
        int $draftId,
        array $richMessage,
        array $options = [],
    ): bool {
        return $this->apiCallBool('sendRichMessageDraft', [
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'rich_message' => $richMessage,
        ], $options);
    }
}
