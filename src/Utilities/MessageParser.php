<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use stdClass;
use TGbotPHP\Framework\Routing\Command;
use TGbotPHP\Support\Value;

/**
 * Helpers for parsing commands, arguments and entities out of message text.
 */
class MessageParser
{
    /**
     * Parse "/command@bot arguments".
     *
     * @return array{command: string, args: string, username: ?string}|null
     */
    public static function parseCommand(string $text): ?array
    {
        $command = Command::parse($text);

        if ($command === null) {
            return null;
        }

        return [
            'command' => $command->name,
            'args' => $command->args,
            'username' => $command->username,
        ];
    }

    /**
     * Split command arguments on whitespace, honouring "double quoted" values.
     *
     * @return string[]
     */
    public static function parseArguments(string $args): array
    {
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|(\S+)/u', $args, $matches, PREG_SET_ORDER);

        return array_map(
            // Inside quotes, only \" and \\ are escapes
            static fn(array $match): string => ($match[2] ?? '') !== ''
                ? $match[2]
                : (string) preg_replace('/\\\\(["\\\\])/', '$1', $match[1] ?? ''),
            $matches,
        );
    }

    /**
     * Text of the entities of a type ("mention", "hashtag", "url", "bot_command"...) in a message.
     *
     * Reads the entities Telegram detected, from `entities` or `caption_entities`.
     * Their offsets count UTF-16 code units, so emoji and other characters
     * outside the Basic Multilingual Plane are handled.
     *
     * @return list<string>
     */
    public static function entities(stdClass $message, string $type): array
    {
        [$text, $entities] = self::textAndEntities($message);
        $characters = self::characters($text);
        $found = [];

        foreach ($entities as $entity) {
            $range = self::entityRange($entity, $type);

            if ($range !== null) {
                $found[] = self::utf16Slice($characters, $range[0], $range[1]);
            }
        }

        return $found;
    }

    /**
     * @return array{string, list<mixed>} The text (or caption) and its entities
     */
    private static function textAndEntities(stdClass $message): array
    {
        $text = Value::nullableString(Value::path($message, 'text'));
        $entities = $text === null ? Value::path($message, 'caption_entities') : Value::path($message, 'entities');

        $text ??= Value::string(Value::path($message, 'caption'));

        return [$text, is_array($entities) ? array_values($entities) : []];
    }

    /**
     * @return list<string> UTF-8 characters of the text
     */
    private static function characters(string $text): array
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $characters === false ? [] : $characters;
    }

    /**
     * @return array{int, int}|null Offset and length when the entity has the type
     */
    private static function entityRange(mixed $entity, string $type): ?array
    {
        $offset = Value::nullableInt(Value::path($entity, 'offset'));
        $length = Value::nullableInt(Value::path($entity, 'length'));

        return Value::path($entity, 'type') === $type && $offset !== null && $length !== null
            ? [$offset, $length]
            : null;
    }

    /**
     * @param list<string> $characters UTF-8 characters
     */
    private static function utf16Slice(array $characters, int $offset, int $length): string
    {
        $slice = '';
        $position = 0;

        foreach ($characters as $character) {
            if ($position >= $offset + $length) {
                break;
            }

            if ($position >= $offset) {
                $slice .= $character;
            }

            // Characters outside the Basic Multilingual Plane (4 bytes in UTF-8) take two UTF-16 units
            $position += strlen($character) === 4 ? 2 : 1;
        }

        return $slice;
    }

    /**
     * @return string[]
     */
    public static function extractMentions(string $text): array
    {
        // Telegram usernames are 5 to 32 characters
        if (preg_match_all('/(?<![\w@])@(\w{5,32})/', $text, $matches) > 0) {
            return $matches[1];
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function extractHashtags(string $text): array
    {
        if (preg_match_all('/(?<!\w)#(\w+)/u', $text, $matches) > 0) {
            return $matches[1];
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function extractUrls(string $text): array
    {
        if (preg_match_all('/https?:\/\/[^\s<>"]+/u', $text, $matches) > 0) {
            return array_map(static fn(string $url): string => rtrim($url, '.,;:!?)'), $matches[0]);
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function extractEmails(string $text): array
    {
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches) > 0) {
            return $matches[0];
        }

        return [];
    }

    /** Remove common Markdown emphasis markers from text. */
    public static function stripMarkdown(string $text): string
    {
        $patterns = [
            '/\*\*(.+?)\*\*/s',
            '/\*(.+?)\*/s',
            '/__(.+?)__/s',
            // A word-internal underscore is snake_case, not Markdown italic
            '/(?<!\w)_(?!\s)(.+?)(?<!\s)_(?!\w)/s',
            '/~(.+?)~/s',
            '/`(.+?)`/s',
        ];

        foreach ($patterns as $pattern) {
            $text = (string) preg_replace($pattern, '$1', $text);
        }

        return $text;
    }
}
