<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

/**
 * Reply markup helpers
 *
 * Static helpers build complete markups from "text => callback data" maps.
 * For mixed buttons use the fluent builder:
 *
 *     Keyboard::inlineBuilder()
 *         ->button('Yes', 'confirm:yes')->button('No', 'confirm:no')
 *         ->row()
 *         ->url('Docs', 'https://core.telegram.org/bots/api');
 */
class Keyboard
{
    /**
     * Single row of callback buttons
     *
     * @param array<int|string, string> $buttons text => callback data
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function inline(array $buttons): array
    {
        return ['inline_keyboard' => [self::callbackButtons($buttons)]];
    }

    /**
     * Single row of URL buttons
     *
     * @param array<int|string, string> $links text => URL
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function links(array $links): array
    {
        $urlButtons = [];
        foreach ($links as $text => $url) {
            $urlButtons[] = self::urlButton((string) $text, $url);
        }

        return ['inline_keyboard' => [$urlButtons]];
    }

    /**
     * @param array<int|string, string> $buttons text => callback data
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function row(array $buttons): array
    {
        return self::inline($buttons);
    }

    /**
     * Callback buttons laid out in a grid
     *
     * @param array<int|string, string> $buttons text => callback data
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function grid(array $buttons, int $cols = 2): array
    {
        $cols = max(1, $cols);

        return ['inline_keyboard' => array_map(
            static fn(array $chunk): array => self::callbackButtons($chunk),
            array_chunk($buttons, $cols, true)
        )];
    }

    /**
     * @param array<int|string, string> $items text => callback data
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function menu(array $items, int $itemsPerRow = 1): array
    {
        return self::grid($items, $itemsPerRow);
    }

    /**
     * Inline keyboard with previous/next navigation for paginated content
     *
     * @param string $callbackPrefix Buttons send "$callbackPrefix$page"
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public static function pagination(int $currentPage, int $totalPages, string $callbackPrefix = 'page:'): array
    {
        $row = [];

        if ($currentPage > 1) {
            $row[] = self::button('« ' . ($currentPage - 1), $callbackPrefix . ($currentPage - 1));
        }

        $row[] = self::button("$currentPage / $totalPages", $callbackPrefix . $currentPage);

        if ($currentPage < $totalPages) {
            $row[] = self::button(($currentPage + 1) . ' »', $callbackPrefix . ($currentPage + 1));
        }

        return ['inline_keyboard' => [$row]];
    }

    /**
     * Custom reply keyboard
     *
     * @param array<int, array<int, string|array<string, mixed>>> $rows Rows of button texts or KeyboardButton objects
     * @return array<string, mixed>
     */
    public static function reply(
        array $rows,
        bool $resize = true,
        bool $oneTime = false,
        ?string $placeholder = null,
        bool $selective = false
    ): array {
        $keyboard = array_map(
            static fn(array $row): array => array_map(
                static fn(string|array $button): array => is_string($button) ? ['text' => $button] : $button,
                array_values($row)
            ),
            array_values($rows)
        );

        return array_filter([
            'keyboard' => $keyboard,
            'resize_keyboard' => $resize ? true : null,
            'one_time_keyboard' => $oneTime ? true : null,
            'input_field_placeholder' => $placeholder,
            'selective' => $selective ? true : null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Remove the custom reply keyboard
     *
     * @return array<string, bool>
     */
    public static function remove(bool $selective = false): array
    {
        return $selective ? ['remove_keyboard' => true, 'selective' => true] : ['remove_keyboard' => true];
    }

    /**
     * Force the user to reply to the bot's message
     *
     * @return array<string, mixed>
     */
    public static function forceReply(?string $placeholder = null, bool $selective = false): array
    {
        return array_filter([
            'force_reply' => true,
            'input_field_placeholder' => $placeholder,
            'selective' => $selective ? true : null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    public static function button(string $text, string $callbackData): array
    {
        if (strlen($callbackData) > 64) {
            throw new \InvalidArgumentException('callback_data must be at most 64 bytes');
        }

        return ['text' => $text, 'callback_data' => $callbackData];
    }

    /**
     * @return array<string, string>
     */
    public static function urlButton(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    public static function webAppButton(string $text, string $url): array
    {
        return ['text' => $text, 'web_app' => ['url' => $url]];
    }

    /**
     * Fluent inline keyboard builder
     */
    public static function inlineBuilder(): InlineKeyboard
    {
        return new InlineKeyboard();
    }

    /**
     * @param array<int|string, string> $buttons
     * @return array<int, array<string, string>>
     */
    private static function callbackButtons(array $buttons): array
    {
        $row = [];
        foreach ($buttons as $text => $data) {
            $row[] = self::button((string) $text, $data);
        }

        return $row;
    }
}
