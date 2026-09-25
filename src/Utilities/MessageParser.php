<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use stdClass;
use TGbotPHP\Framework\Routing\Command;
use TGbotPHP\Support\Value;

class MessageParser
{
    /**
     * Parse "/command@bot arguments"
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
     * Split command arguments on whitespace, honouring "double quoted" values
     *
     * @return string[]
     */
    public static function parseArguments(string $args): array
    {
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|(\S+)/u', $args, $matches, PREG_SET_ORDER);

        return array_map(
            // Inside quotes, only \" and \\ are escapes
            static fn(array $match): string => ($match[2] ?? '') !== '' ? $match[2] : (string) preg_replace('/\\\\(["\\\\])/', '$1', $match[1] ?? ''),
            $matches,
        );
    }

    /**
     * Text of the entities of a type ("mention", "hashtag", "url", "bot_command"...) in a message
     *
     * Reads the entities Telegram detected, from `entities` or `caption_entities`.
     * Their offsets count UTF-16 code units, so emoji and other characters
     * outside the Basic Multilingual Plane are handled.
     *
     * @return list<string>
     */
    public static function entities(stdClass $message, string $type): array
    {
        $text = Value::nullableString(Value::path($message, 'text'));
        $entities = Value::path($message, 'entities');

        if ($text === null) {
            $text = Value::string(Value::path($message, 'caption'));
            $entities = Value::path($message, 'caption_entities');
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $characters = $characters === false ? [] : $characters;
        $found = [];

        foreach (is_array($entities) ? $entities : [] as $entity) {
            $offset = Value::nullableInt(Value::path($entity, 'offset'));
            $length = Value::nullableInt(Value::path($entity, 'length'));

            if (Value::path($entity, 'type') === $type && $offset !== null && $length !== null) {
                $found[] = self::utf16Slice($characters, $offset, $length);
            }
        }

        return $found;
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
        if (preg_match_all('/(?<![\w@])@(\w{3,32})/u', $text, $matches) > 0) {
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

    public static function stripMarkdown(string $text): string
    {
        $patterns = [
            '/\*\*(.+?)\*\*/s',
            '/\*(.+?)\*/s',
            '/__(.+?)__/s',
            '/_(.+?)_/s',
            '/~(.+?)~/s',
            '/`(.+?)`/s',
        ];

        foreach ($patterns as $pattern) {
            $text = (string) preg_replace($pattern, '$1', $text);
        }

        return $text;
    }
}
