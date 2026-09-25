<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Concerns;

use stdClass;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Support\Payload;
use TGbotPHP\Support\Value;

/**
 * Shortcuts to answer the update being handled
 *
 * Used by Bot, which provides the API methods.
 */
trait RespondsToUpdates
{
    /**
     * Send a message where a message or callback query came from
     *
     * The reply goes to the same chat and, when there is one, the same forum
     * topic, business connection or direct messages topic.
     *
     * @param array<string, mixed> $options sendMessage parameters; they override the defaults (parse_mode HTML)
     * @return array<string, mixed>
     */
    public function reply(stdClass $payload, string $text, array $options = []): array
    {
        return $this->sendMessage(Payload::requireChatId($payload), $text, options: $options + Payload::replyTarget($payload));
    }

    /**
     * Edit the text of the message a callback query button belongs to, including inline messages
     *
     * @param array<string, mixed> $options editMessageText parameters (reply_markup, parse_mode...)
     * @return array<string, mixed>|bool The edited message, true for inline messages, false when nothing changed
     */
    public function edit(stdClass $callbackQuery, string $text, array $options = []): array|bool
    {
        $inlineMessageId = Value::nullableString(Value::path($callbackQuery, 'inline_message_id'));
        $messageId = Value::nullableInt(Value::path($callbackQuery, 'message', 'message_id'));

        if ($inlineMessageId === null && $messageId === null) {
            throw new \InvalidArgumentException('The callback query has no message to edit');
        }

        try {
            return $inlineMessageId !== null
                ? $this->editMessageText(null, null, $text, options: ['inline_message_id' => $inlineMessageId] + $options)
                : $this->editMessageText(Payload::requireChatId($callbackQuery), $messageId, $text, options: $options);
        } catch (ApiException $e) {
            // Pressing the button of the page already shown is not an error
            return $e->isMessageNotModified() ? false : throw $e;
        }
    }

    public function answer(stdClass $callbackQuery, ?string $text = null, bool $showAlert = false): bool
    {
        $id = Value::nullableString(Value::path($callbackQuery, 'id'))
            ?? throw new \InvalidArgumentException('The payload is not a callback query');

        return $this->answerCallbackQuery($id, $text, $showAlert);
    }

    /**
     * Chat id of a message, a callback query or any payload carrying a chat
     */
    public function chatId(stdClass $payload): int|string
    {
        return Payload::requireChatId($payload);
    }
}
