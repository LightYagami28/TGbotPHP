<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

use Throwable;

/**
 * Redact credentials in log lines.
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
    private const string SECRET_KEY = '/(?:^|_)(?:token|secret|password|authorization|cookie|api_key|'
        . 'credential|session_id|init_data|initdata)(?:_|$)/i';

    private function __construct()
    {
        // Static helpers only
    }

    /** Replace tokens, URL credentials and key=value secrets with a placeholder. */
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

    /** Format an exception message safely for single-line log output. */
    public static function safeExceptionMessage(Throwable $e): string
    {
        $message = str_replace(["\r", "\n"], ['\r', '\n'], $e->getMessage());

        return self::text($message);
    }

    /**
     * Redact a log context recursively, up to eight levels deep.
     *
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

    /** Redact one value, using its key to detect credentials. */
    private static function value(mixed $value, string $key, int $depth): mixed
    {
        if ($depth >= 8 || self::isSecretKey($key)) {
            return '<redacted>';
        }

        if (is_array($value)) {
            return self::children($value, $depth + 1);
        }

        return is_string($value) ? self::text($value) : $value;
    }

    /** Whether a field name holds a credential. */
    private static function isSecretKey(string $key): bool
    {
        return $key !== '' && preg_match(self::SECRET_KEY, $key) === 1;
    }

    /**
     * @param array<array-key, mixed> $children
     * @return array<array-key, mixed>
     */
    private static function children(array $children, int $depth): array
    {
        foreach ($children as $childKey => $child) {
            $children[$childKey] = self::value($child, is_string($childKey) ? $childKey : '', $depth);
        }

        return $children;
    }
}
