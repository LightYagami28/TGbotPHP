<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

/**
 * Redacts credentials in log lines
 *
 * Bot tokens, URL userinfo and fields whose name is a secret are replaced.
 * Operational ids such as chat_id and user_id, and message text, are kept:
 * a log that hides them cannot be used to run the bot.
 */
final class LogRedactor
{
    /**
     * Field names that hold a credential. Matched as a whole segment so
     * chat_id, user and text are not treated as secrets.
     */
    private const string SECRET_KEY = '/(?:^|_)(?:token|secret|password|authorization|cookie|api_key|credential|session_id|init_data|initdata)(?:_|$)/i';

    private function __construct()
    {
        // Static helpers only
    }

    public static function text(string $text): string
    {
        $text = (string) preg_replace('/\b\d+:[A-Za-z0-9_-]{6,}\b/', '<redacted>', $text);
        $text = (string) preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i', '$1<redacted>@', $text);
        $text = (string) preg_replace('/\b(Authorization\s*:\s*)(?:Bearer|Basic)\s+\S+/i', '$1<redacted>', $text);

        return (string) preg_replace(
            '/\b((?:[a-z0-9_]*token|password|secret|api_key|cookie|session_id)\s*[=:]\s*)\S+/i',
            '$1<redacted>',
            $text,
        );
    }

    /**
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    public static function context(array $context): array
    {
        foreach ($context as $key => $value) {
            $context[$key] = self::value($value, is_string($key) ? $key : '', 0);
        }

        return $context;
    }

    private static function value(mixed $value, string $key, int $depth): mixed
    {
        if ($depth >= 8) {
            return '<redacted>';
        }

        if ($key !== '' && preg_match(self::SECRET_KEY, $key) === 1) {
            return '<redacted>';
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $value[$childKey] = self::value($child, is_string($childKey) ? $childKey : '', $depth + 1);
            }

            return $value;
        }

        return is_string($value) ? self::text($value) : $value;
    }
}
