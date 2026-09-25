<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

/**
 * A bot command parsed from a message: "/name@botusername arguments"
 */
final readonly class Command
{
    private const string SYNTAX = '/^\/(\w{1,64})(?:@(\w{3,}))?(?:\s+(.*))?\z/s';

    private function __construct(
        public string $name,
        public ?string $username,
        public string $args,
    ) {
    }

    public static function parse(string $text): ?self
    {
        if (preg_match(self::SYNTAX, $text, $matches) !== 1) {
            return null;
        }

        $username = $matches[2] ?? '';

        return new self(
            strtolower($matches[1]),
            $username !== '' ? strtolower($username) : null,
            trim($matches[3] ?? ''),
        );
    }

    /**
     * "start", "/start", "/START@my_bot" all normalize to "start"
     */
    public static function normalizeName(string $command): string
    {
        $name = strtolower(ltrim(trim($command), '/'));
        $at = strpos($name, '@');

        return $at === false ? $name : substr($name, 0, $at);
    }

    /**
     * False when the command explicitly names another bot (/start@other_bot)
     */
    public function isAddressedTo(?string $botUsername): bool
    {
        return $this->username === null || $botUsername === null || $this->username === $botUsername;
    }
}
