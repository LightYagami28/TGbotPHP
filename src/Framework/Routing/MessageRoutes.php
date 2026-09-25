<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

use stdClass;
use TGbotPHP\Support\Value;

/**
 * Routes for messages, tried in order: commands, conversation state, text patterns, fallback
 */
final class MessageRoutes
{
    /** @var array<string, callable> Keyed by "/name" so numeric names stay strings */
    private array $commands = [];

    /** @var array<string, callable> Keyed by "#state" so numeric names stay strings */
    private array $states = [];

    private readonly PatternTable $texts;

    /** @var callable|null */
    private $fallback = null;

    /** @var callable|null */
    private $unknownCommand = null;

    public function __construct()
    {
        $this->texts = new PatternTable();
    }

    public function addCommand(string $command, callable $handler): void
    {
        $this->commands['/' . Command::normalizeName($command)] = $handler;
    }

    public function addState(string $state, callable $handler): void
    {
        $this->states['#' . $state] = $handler;
    }

    public function addText(string $pattern, callable $handler): void
    {
        $this->texts->add($pattern, $handler);
    }

    public function setFallback(callable $handler): void
    {
        $this->fallback = $handler;
    }

    public function setUnknownCommand(callable $handler): void
    {
        $this->unknownCommand = $handler;
    }

    /**
     * @return list<string>
     */
    public function commandNames(): array
    {
        return array_map(static fn(string $key): string => substr($key, 1), array_keys($this->commands));
    }

    /**
     * @param array<string, mixed> $stateData
     * @return Route|false|null The route, false for a command addressed to another bot, null when nothing matched
     */
    public function resolve(stdClass $message, ?string $state, array $stateData, ?string $botUsername): Route|false|null
    {
        $text = Value::nullableString(Value::path($message, 'text'));
        $content = $text ?? Value::nullableString(Value::path($message, 'caption'));
        $command = $text !== null ? Command::parse($text) : null;

        if ($command !== null && !$command->isAddressedTo($botUsername)) {
            return false;
        }

        return $this->command($command)
            ?? $this->state($state, $stateData)
            ?? ($content !== null ? $this->texts->find($content) : null)
            ?? $this->fallback($text);
    }

    private function command(?Command $command): ?Route
    {
        if ($command === null) {
            return null;
        }

        $handler = $this->commands['/' . $command->name] ?? $this->unknownCommand;

        return $handler !== null ? new Route($handler, [$command->args]) : null;
    }

    /**
     * @param array<string, mixed> $stateData
     */
    private function state(?string $state, array $stateData): ?Route
    {
        $handler = $state !== null ? $this->states['#' . $state] ?? null : null;

        return $handler !== null ? new Route($handler, [$stateData]) : null;
    }

    private function fallback(?string $text): ?Route
    {
        return $text !== null && $this->fallback !== null ? new Route($this->fallback) : null;
    }
}
