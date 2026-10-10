<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

/** Best-effort protection for application logs; never log arbitrary user payloads. */
final class LogRedactor
{
    public static function text(string $text): string
    {
        $text = (string) preg_replace('/\b[0-9]+:[A-Za-z0-9_-]{6,}\b/', '<redacted>', $text);
        $text = (string) preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i', '$1<redacted>@', $text);
        $text = (string) preg_replace('/\b(Authorization\s*:\s*)(?:Bearer|Basic)\s+[^\s,;]+/i', '$1<redacted>', $text);
        return (string) preg_replace('/\b((?:[a-z_]*token|password|secret|api_key|cookie|session_id)\s*[=:]\s*)[^\s&;,]+/i', '$1<redacted>', $text);
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public static function context(array $context): array
    {
        foreach ($context as $key => $value) {
            $context[$key] = self::value($value, $key, 0);
        }
        return $context;
    }

    private static function value(mixed $value, string $key, int $depth): mixed
    {
        if ($depth >= 8 || preg_match('/token|secret|password|authorization|cookie|session|credential|api.?key|text|caption|phone|email|address|location|user|chat|payload|body|init.?data|passport/i', $key) === 1) {
            return '<redacted>';
        }
        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $value[$childKey] = self::value($child, (string) $childKey, $depth + 1);
            }
            return $value;
        }
        if (is_resource($value)) {
            return '<redacted>';
        }
        if (is_object($value)) {
            if ($value instanceof \stdClass) {
                $clone = clone $value;
                foreach (get_object_vars($clone) as $childKey => $child) {
                    $clone->$childKey = self::value($child, (string) $childKey, $depth + 1);
                }
                return $clone;
            }
            return $value;
        }
        return is_string($value) ? self::text($value) : $value;
    }
}
