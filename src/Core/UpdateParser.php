<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

use JsonException;
use stdClass;
use TGbotPHP\Support\Value;

/**
 * Parse and inspect Telegram webhook updates.
 *
 * Telegram sends a JSON serialized Update object. This class validates the
 * envelope, converts it to stdClass and offers typed helpers to find the
 * update type, payload, chat and originating user.
 */
class UpdateParser
{
    /**
     * Known update types, in the order they appear in the Update object.
     *
     * The order matters: getType() returns the first field present, matching
     * the order used by the official Bot API specification.
     *
     * @var list<string>
     */
    public const array UPDATE_TYPES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'business_connection',
        'business_message',
        'edited_business_message',
        'deleted_business_messages',
        'guest_message',
        'message_reaction',
        'message_reaction_count',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'purchased_paid_media',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
        'chat_boost',
        'removed_chat_boost',
        'managed_bot',
        'subscription',
        'stopped_message_generation',
        'widget_query',
        'shared_widget_query',
    ];

    /**
     * Decode and validate a webhook body.
     *
     * A valid update is a JSON object carrying a non-negative integer
     * update_id smaller than PHP_INT_MAX.
     *
     * @throws JsonException When the body is not valid JSON or not a valid update
     */
    public static function parse(string $json): stdClass
    {
        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Invalid webhook JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!$decoded instanceof stdClass || !self::hasValidUpdateId($decoded)) {
            throw new JsonException('Invalid webhook JSON: update_id must be a non-negative integer below PHP_INT_MAX');
        }

        return $decoded;
    }

    /**
     * Whether update_id is an integer Telegram could actually issue.
     */
    private static function hasValidUpdateId(stdClass $update): bool
    {
        $id = $update->update_id ?? null;

        return is_int($id) && $id >= 0 && $id !== PHP_INT_MAX;
    }

    /**
     * Convert an update decoded as an associative array (e.g. from getUpdates)
     * to the same stdClass representation used for webhooks.
     *
     * @param array<string, mixed> $update Update decoded as an associative array
     *
     * @throws JsonException When the array cannot be encoded or is not a valid update
     */
    public static function fromArray(array $update): stdClass
    {
        return self::parse(json_encode($update, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the type of an update ("message", "callback_query", ...).
     *
     * Unknown but object-valued fields are still reported, so new update
     * types work before they are added to {@see self::UPDATE_TYPES}.
     */
    public static function getType(stdClass $update): ?string
    {
        foreach (self::UPDATE_TYPES as $type) {
            if (isset($update->$type)) {
                return $type;
            }
        }

        return self::unknownType($update);
    }

    /**
     * Fall back to the first object-valued field other than update_id.
     */
    private static function unknownType(stdClass $update): ?string
    {
        foreach (get_object_vars($update) as $key => $value) {
            if (self::isTypeEntry($key, $value)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Whether a field looks like the payload of an update type.
     */
    private static function isTypeEntry(int|string $key, mixed $value): bool
    {
        return is_string($key) && $key !== 'update_id' && $value instanceof stdClass;
    }

    /**
     * Get the payload of an update (the Message, CallbackQuery, ... object).
     */
    public static function getPayload(stdClass $update): ?stdClass
    {
        $type = self::getType($update);

        return $type !== null && $update->$type instanceof stdClass ? $update->$type : null;
    }

    /**
     * Extract the chat an update belongs to, when there is one.
     *
     * Callback queries carry the chat inside the attached message rather than
     * directly on the payload.
     */
    public static function getChat(stdClass $update): ?stdClass
    {
        $payload = self::getPayload($update);

        if ($payload === null) {
            return null;
        }

        if (isset($payload->chat) && $payload->chat instanceof stdClass) {
            return $payload->chat;
        }

        return Value::object(Value::path($payload, 'message', 'chat'));
    }

    /**
     * Extract the user who triggered an update, when there is one.
     *
     * Most payloads use `from`; some, such as message reactions, use `user`.
     */
    public static function getUser(stdClass $update): ?stdClass
    {
        $payload = self::getPayload($update);

        if ($payload === null) {
            return null;
        }

        $from = $payload->from ?? null;

        return $from instanceof stdClass ? $from : Value::object($payload->user ?? null);
    }

    /** Check if the update contains a message. */
    public static function hasMessage(stdClass $update): bool
    {
        return isset($update->message);
    }

    /** Check if the update contains a callback query. */
    public static function hasCallbackQuery(stdClass $update): bool
    {
        return isset($update->callback_query);
    }

    /** Check if the update contains an inline query. */
    public static function hasInlineQuery(stdClass $update): bool
    {
        return isset($update->inline_query);
    }
}
