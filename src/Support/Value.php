<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

use stdClass;

/**
 * Type-safe readers for untyped data (decoded JSON, update payloads, cache entries)
 *
 * Telegram payloads are decoded JSON: every field is `mixed` until checked.
 * These helpers never cast arrays or objects, so malformed input can never
 * turn into "Array" strings or accidental integers.
 */
final class Value
{
    private function __construct()
    {
        // Static helpers only
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return self::nullableInt($value) ?? $default;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) && is_finite($value) => (int) $value,
            is_string($value) && preg_match('/^-?\d{1,19}\z/', $value) === 1 => (int) $value,
            default => null,
        };
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return self::nullableString($value) ?? $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }

    /**
     * Chat or user identifier (Telegram ids are integers, usernames like "@channel" are strings)
     */
    public static function id(mixed $value): int|string|null
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && $value !== '' => self::nullableInt($value) ?? $value,
            default => null,
        };
    }

    /**
     * Read an environment variable; null when it is missing or empty
     */
    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function object(mixed $value): ?stdClass
    {
        return $value instanceof stdClass ? $value : null;
    }

    /**
     * Read a nested property: Value::path($update, 'message', 'chat', 'id')
     */
    public static function path(mixed $value, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if ($value instanceof stdClass && property_exists($value, $key)) {
                $value = $value->$key;
            } elseif (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Keep the string keys of an array (JSON objects decoded as arrays)
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * Check a value is a JSON object decoded as an associative array
     *
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    public static function isMap(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check a value is a list of JSON objects
     *
     * @phpstan-assert-if-true list<array<string, mixed>> $value
     */
    public static function isListOfMaps(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!self::isMap($item)) {
                return false;
            }
        }

        return true;
    }
}
