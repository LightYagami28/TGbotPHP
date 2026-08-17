<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

class MessageParser
{
    public static function parseCommand(string $text): ?array
    {
        if (!str_starts_with($text, '/')) {
            return null;
        }

        $parts = explode(' ', $text, 2);
        $command = substr($parts[0], 1);
        $args = $parts[1] ?? '';

        return [
            'command' => $command,
            'args' => $args,
        ];
    }

    public static function extractMentions(string $text): array
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        return $matches[1] ?? [];
    }

    public static function extractHashtags(string $text): array
    {
        preg_match_all('/#(\w+)/', $text, $matches);
        return $matches[1] ?? [];
    }

    public static function extractUrls(string $text): array
    {
        preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
        return $matches[0] ?? [];
    }

    public static function extractEmails(string $text): array
    {
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches);
        return $matches[0] ?? [];
    }

    public static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);
        $text = preg_replace('/`(.+?)`/', '$1', $text);

        return $text;
    }
}
