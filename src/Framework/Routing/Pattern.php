<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

/**
 * A route pattern, compiled once
 *
 * - exact:    'menu'
 * - wildcard: 'page:*'           the part matched by "*" is captured
 * - regex:    '/^page:(\d+)$/'   delimiters / # or ~, with optional flags
 */
final readonly class Pattern
{
    /** Regular expression, or null for an exact match */
    private ?string $regex;

    public function __construct(public string $source)
    {
        $this->regex = match (true) {
            self::isRegex($source) => $source,
            str_contains($source, '*') => '/^' . str_replace('\*', '(.*)', preg_quote($source, '/')) . '\z/su',
            default => null,
        };
    }

    public function isExact(): bool
    {
        return $this->regex === null;
    }

    /**
     * @return array<int|string, string>|null Captured groups (index 0 is the full match), or null
     */
    public function match(string $value): ?array
    {
        if ($this->regex === null) {
            return $this->source === $value ? [$value] : null;
        }

        return preg_match($this->regex, $value, $matches) === 1 ? $matches : null;
    }

    private static function isRegex(string $pattern): bool
    {
        if (strlen($pattern) < 3 || !in_array($pattern[0], ['/', '#', '~'], true)) {
            return false;
        }

        $end = strrpos($pattern, $pattern[0]);
        $flags = $end === false ? '' : substr($pattern, $end + 1);

        // A string that only looks like a regex ("/path/") but does not compile is matched literally
        return $end > 0 && preg_match('/^[imsxuUXJD]*\z/', $flags) === 1 && @preg_match($pattern, '') !== false;
    }
}
