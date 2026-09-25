<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

use stdClass;

/**
 * Reads the common fields of update payloads (Message, CallbackQuery, ...)
 */
final class Payload
{
    private function __construct()
    {
        // Static helpers only
    }

    /**
     * The Message of a "message" update
     */
    public static function message(stdClass $update): ?stdClass
    {
        return Value::object(Value::path($update, 'message'));
    }

    /**
     * Chat id of a message, or of the message a callback query belongs to
     */
    public static function chatId(stdClass $payload): int|string|null
    {
        return Value::id(Value::path($payload, 'chat', 'id') ?? Value::path($payload, 'message', 'chat', 'id'));
    }

    /**
     * Id of the user who sent a message or pressed a button
     */
    public static function userId(stdClass $payload): int|string|null
    {
        return Value::id(Value::path($payload, 'from', 'id'));
    }

    /**
     * The payload itself for a message, the attached message for a callback query
     */
    public static function sourceMessage(stdClass $payload): stdClass
    {
        return Value::object(Value::path($payload, 'message')) ?? $payload;
    }

    /**
     * Forum topic a message was sent in, when it was sent in one
     */
    public static function topicId(stdClass $payload): ?int
    {
        $message = self::sourceMessage($payload);

        return Value::path($message, 'is_topic_message') === true
            ? Value::nullableInt(Value::path($message, 'message_thread_id'))
            : null;
    }

    public static function requireChatId(stdClass $payload): int|string
    {
        return self::chatId($payload) ?? throw new \InvalidArgumentException('The payload has no chat');
    }
}
