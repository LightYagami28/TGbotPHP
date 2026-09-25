<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use TGbotPHP\Framework\Routing\Command;

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
            static fn(array $match): string => ($match[2] ?? '') !== '' ? $match[2] : stripcslashes($match[1] ?? ''),
            $matches
        );
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
