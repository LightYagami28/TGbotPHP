<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

/**
 * Safe text formatting for the HTML and MarkdownV2 parse modes
 *
 * Always escape user supplied text before embedding it in formatted messages.
 *
 *     $bot->sendMessage($chatId, Formatter::bold('Hello') . ' ' . Formatter::escape($name));
 */
final class Formatter
{
    /**
     * Escape text for parse_mode HTML
     *
     * Telegram only understands the &lt; &gt; &amp; &quot; named entities and
     * numeric ones, so quotes are escaped as &quot; and &#039; (never &apos;).
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape text for parse_mode MarkdownV2
     */
    public static function escapeMarkdownV2(string $text): string
    {
        return (string) preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
    }

    public static function bold(string $text): string
    {
        return '<b>' . self::escape($text) . '</b>';
    }

    public static function italic(string $text): string
    {
        return '<i>' . self::escape($text) . '</i>';
    }

    public static function underline(string $text): string
    {
        return '<u>' . self::escape($text) . '</u>';
    }

    public static function strike(string $text): string
    {
        return '<s>' . self::escape($text) . '</s>';
    }

    public static function spoiler(string $text): string
    {
        return '<tg-spoiler>' . self::escape($text) . '</tg-spoiler>';
    }

    public static function code(string $text): string
    {
        return '<code>' . self::escape($text) . '</code>';
    }

    public static function pre(string $text, ?string $language = null): string
    {
        if ($language === null || $language === '') {
            return '<pre>' . self::escape($text) . '</pre>';
        }

        return '<pre><code class="language-' . self::escape($language) . '">' . self::escape($text) . '</code></pre>';
    }

    public static function quote(string $text, bool $expandable = false): string
    {
        return '<blockquote' . ($expandable ? ' expandable' : '') . '>' . self::escape($text) . '</blockquote>';
    }

    public static function link(string $text, string $url): string
    {
        return '<a href="' . self::escape($url) . '">' . self::escape($text) . '</a>';
    }

    /**
     * Mention a user by id (works for users without a username)
     */
    public static function mention(int $userId, string $name): string
    {
        return self::link($name, 'tg://user?id=' . $userId);
    }
}
