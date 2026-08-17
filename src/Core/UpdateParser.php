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
     * Parse webhook JSON
     *
     * @throws JsonException
     */
    public static function parse(string $json): stdClass
    {
        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($decoded->update_id)) {
                throw new JsonException('Missing update_id in webhook');
            }

            return $decoded;
        } catch (JsonException $e) {
            throw new JsonException("Invalid webhook JSON: " . $e->getMessage());
        }
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
