<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

/**
 * Handlers indexed by pattern
 *
 * Exact patterns are looked up first, then wildcard and regex patterns in
 * registration order.
 */
final class PatternTable
{
    /** @var array<string, callable> */
    private array $exact = [];

    /** @var list<array{Pattern, callable}> */
    private array $patterns = [];

    public function add(string $pattern, callable $handler): void
    {
        $compiled = new Pattern($pattern);

        if ($compiled->isExact()) {
            // A string key: PHP would turn a numeric key such as "123" into an integer
            $this->exact["=$pattern"] = $handler;
            return;
        }

        $this->patterns[] = [$compiled, $handler];
    }

    /**
     * @return array{callable, array<int|string, string>}|null The handler and the captured groups
     */
    public function find(string $value): ?array
    {
        $handler = $this->exact["=$value"] ?? null;

        if ($handler !== null) {
            return [$handler, [$value]];
        }

        foreach ($this->patterns as [$pattern, $candidate]) {
            $matches = $pattern->match($value);

            if ($matches !== null) {
                return [$candidate, $matches];
            }
        }

        return null;
    }
}
