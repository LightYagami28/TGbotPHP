<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

use JsonException;
use stdClass;

/**
 * Parse and validate Telegram webhook updates
 */
class UpdateParser
{
    /**
     * Known update types, in the order they appear in the Update object
     */
    public const UPDATE_TYPES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'business_connection',
        'business_message',
        'edited_business_message',
        'deleted_business_messages',
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
    ];

    /**
     * Parse webhook JSON
     *
     * @throws JsonException
     */
    public static function parse(string $json): stdClass
    {
        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Invalid webhook JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!$decoded instanceof stdClass || !isset($decoded->update_id)) {
            throw new JsonException('Invalid webhook JSON: missing update_id');
        }

        return $decoded;
    }

    /**
     * Convert an update decoded as an associative array (e.g. from getUpdates) to an object
     *
     * @param array<string, mixed> $update
     *
     * @throws JsonException
     */
    public static function fromArray(array $update): stdClass
    {
        return self::parse(json_encode($update, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the type of an update ("message", "callback_query", ...)
     */
    public static function getType(stdClass $update): ?string
    {
        foreach (self::UPDATE_TYPES as $type) {
            if (isset($update->$type)) {
                return $type;
            }
        }

        foreach (get_object_vars($update) as $key => $value) {
            if ($key !== 'update_id' && $value instanceof stdClass) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Get the payload of an update (the Message, CallbackQuery, ... object)
     */
    public static function getPayload(stdClass $update): ?stdClass
    {
        $type = self::getType($update);

        return $type !== null && $update->$type instanceof stdClass ? $update->$type : null;
    }

    /**
     * Extract the chat an update belongs to, when there is one
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

        if (isset($payload->message->chat) && $payload->message->chat instanceof stdClass) {
            return $payload->message->chat;
        }

        return null;
    }

    /**
     * Extract the user who triggered an update, when there is one
     */
    public static function getUser(stdClass $update): ?stdClass
    {
        $payload = self::getPayload($update);

        if ($payload === null) {
            return null;
        }

        foreach (['from', 'user'] as $key) {
            if (isset($payload->$key) && $payload->$key instanceof stdClass) {
                return $payload->$key;
            }
        }

        return null;
    }

    /**
     * Check if update contains message
     */
    public static function hasMessage(stdClass $update): bool
    {
        return isset($update->message);
    }

    /**
     * Check if update contains callback query
     */
    public static function hasCallbackQuery(stdClass $update): bool
    {
        return isset($update->callback_query);
    }

    /**
     * Check if update contains inline query
     */
    public static function hasInlineQuery(stdClass $update): bool
    {
        return isset($update->inline_query);
    }
}
