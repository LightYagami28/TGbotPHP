<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;

/**
 * Ephemeral message methods from Telegram Bot API
 *
 * Edit and delete messages shown to a single user in a group.
 *
 * @see https://core.telegram.org/bots/api#editephemeralmessagetext
 */
trait EphemeralMessageMethods
{
    use CallsApi;

    /**
     * Delete an ephemeral message
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#deleteephemeralmessage
     */
    public function deleteEphemeralMessage(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        array $options = [],
    ): bool {
        return $this->apiCallBool('deleteEphemeralMessage', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
        ], $options);
    }

    /**
     * Edit the caption of an ephemeral message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options parse_mode, caption_entities, show_caption_above_media
     *
     * @see https://core.telegram.org/bots/api#editephemeralmessagecaption
     */
    public function editEphemeralMessageCaption(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        ?string $caption = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('editEphemeralMessageCaption', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
            'caption' => $caption,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit the media of an ephemeral message
     *
     * @param array<string, mixed> $media
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#editephemeralmessagemedia
     */
    public function editEphemeralMessageMedia(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        array $media,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('editEphemeralMessageMedia', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
            'media' => $media,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit only the reply markup of an ephemeral message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#editephemeralmessagereplymarkup
     */
    public function editEphemeralMessageReplyMarkup(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('editEphemeralMessageReplyMarkup', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Edit an ephemeral text or rich message
     *
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options parse_mode, entities, rich_message, link_preview_options
     *
     * @see https://core.telegram.org/bots/api#editephemeralmessagetext
     */
    public function editEphemeralMessageText(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        ?string $text = null,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('editEphemeralMessageText', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ], $options);
    }
}
