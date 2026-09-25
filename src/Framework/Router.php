<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Routing\Command;
use TGbotPHP\Framework\Routing\PatternTable;
use TGbotPHP\Support\Value;

/**
 * Routes updates to handlers
 *
 * Handlers receive the payload, the Bot (when a Bot drives the router), then
 * route data:
 *
 *     command:  fn(stdClass $message, Bot $bot, string $args)
 *     hears:    fn(stdClass $message, Bot $bot, array $matches)
 *     callback: fn(stdClass $callbackQuery, Bot $bot, array $matches)
 *     inline:   fn(stdClass $inlineQuery, Bot $bot, array $matches)
 *     state:    fn(stdClass $message, Bot $bot, array $stateData)
 *     update:   fn(stdClass $payload, Bot $bot, stdClass $update)
 *     default:  fn(stdClass $message, Bot $bot)
 *
 * See Routing\Pattern for the pattern syntax.
 */
final class Router
{
    /** @var array<string, callable> Keyed by "/name" so numeric names stay strings */
    private array $commands = [];

    /** @var array<string, callable> Keyed by "#state" so numeric names stay strings */
    private array $states = [];

    /** @var array<string, list<callable>> */
    private array $updateHandlers = [];

    private readonly PatternTable $callbacks;
    private readonly PatternTable $inlineQueries;
    private readonly PatternTable $texts;

    /** @var callable|null */
    private $fallback = null;

    /** @var callable|null */
    private $unknownCommand = null;

    private ?string $botUsername = null;

    private ?Bot $bot = null;

    public function __construct()
    {
        $this->callbacks = new PatternTable();
        $this->inlineQueries = new PatternTable();
        $this->texts = new PatternTable();
    }

    /**
     * Bot passed to every handler as second argument
     */
    public function setBot(?Bot $bot): void
    {
        $this->bot = $bot;
    }

    /**
     * Bot username, used to ignore commands addressed to other bots (/start@other_bot)
     */
    public function setBotUsername(?string $username): void
    {
        $this->botUsername = $username !== null ? strtolower(ltrim($username, '@')) : null;
    }

    public function getBotUsername(): ?string
    {
        return $this->botUsername;
    }

    /**
     * "start", "/start" and "START" are the same command
     */
    public function registerCommand(string $command, callable $handler): void
    {
        $this->commands['/' . Command::normalizeName($command)] = $handler;
    }

    public function registerCallback(string $pattern, callable $handler): void
    {
        $this->callbacks->add($pattern, $handler);
    }

    public function registerInlineQuery(string $pattern, callable $handler): void
    {
        $this->inlineQueries->add($pattern, $handler);
    }

    /**
     * Handler for text messages, or media captions, matching a pattern
     */
    public function registerText(string $pattern, callable $handler): void
    {
        $this->texts->add($pattern, $handler);
    }

    /**
     * Handler for messages received while a conversation is in the given state
     */
    public function registerState(string $state, callable $handler): void
    {
        $this->states['#' . $state] = $handler;
    }

    /**
     * Handler for an update type ("chat_member", "pre_checkout_query", ...), run when no other route matched
     */
    public function registerUpdate(string $type, callable $handler): void
    {
        $this->updateHandlers[$type][] = $handler;
    }

    /**
     * Handler for text messages that match no other route
     */
    public function setDefaultHandler(callable $handler): void
    {
        $this->fallback = $handler;
    }

    public function setUnknownCommandHandler(callable $handler): void
    {
        $this->unknownCommand = $handler;
    }

    /**
     * @param array<string, mixed> $stateData
     * @return bool Whether a handler ran
     */
    public function dispatch(stdClass $update, ?string $state = null, array $stateData = []): bool
    {
        $type = UpdateParser::getType($update);
        $payload = $type !== null ? Value::object($update->$type) : null;

        if ($type === null || $payload === null) {
            return false;
        }

        $handled = match ($type) {
            'message' => $this->handleMessage($payload, $state, $stateData),
            'callback_query' => $this->handleCallback($payload),
            'inline_query' => $this->handleInlineQuery($payload),
            default => false,
        };

        return $handled || $this->runUpdateHandlers($type, $payload, $update);
    }

    /**
     * Order: commands, conversation state, text patterns, fallback
     *
     * @param array<string, mixed> $stateData
     */
    public function handleMessage(stdClass $message, ?string $state = null, array $stateData = []): bool
    {
        $text = Value::nullableString(Value::path($message, 'text'));
        $content = $text ?? Value::nullableString(Value::path($message, 'caption'));
        $command = $text !== null ? $this->routeCommand($text, $message) : null;

        if ($command !== null) {
            return $command;
        }

        return $this->routeState($message, $state, $stateData)
            || $this->routeText($message, $content)
            || $this->routeFallback($message, $text);
    }

    public function handleCallback(stdClass $callback): bool
    {
        return $this->routeByPattern($this->callbacks, Value::string(Value::path($callback, 'data')), $callback);
    }

    public function handleInlineQuery(stdClass $query): bool
    {
        return $this->routeByPattern($this->inlineQueries, Value::string(Value::path($query, 'query')), $query);
    }

    /**
     * @return list<string> Registered command names
     */
    public function getCommands(): array
    {
        return array_map(static fn(string $key): string => substr($key, 1), array_keys($this->commands));
    }

    /**
     * @return bool|null true when handled, false when addressed to another bot, null to try the next routes
     */
    private function routeCommand(string $text, stdClass $message): ?bool
    {
        $command = Command::parse($text);

        if ($command === null) {
            return null;
        }

        if (!$command->isAddressedTo($this->botUsername)) {
            return false;
        }

        $handler = $this->commands['/' . $command->name] ?? $this->unknownCommand;

        if ($handler !== null) {
            $this->invoke($handler, $message, $command->args);
        }

        return $handler !== null ? true : null;
    }

    /**
     * @param array<string, mixed> $stateData
     */
    private function routeState(stdClass $message, ?string $state, array $stateData): bool
    {
        $handler = $state !== null ? $this->states['#' . $state] ?? null : null;

        if ($handler !== null) {
            $this->invoke($handler, $message, $stateData);
        }

        return $handler !== null;
    }

    private function routeText(stdClass $message, ?string $content): bool
    {
        return $content !== null && $this->routeByPattern($this->texts, $content, $message);
    }

    private function routeFallback(stdClass $message, ?string $text): bool
    {
        if ($text === null || $this->fallback === null) {
            return false;
        }

        $this->invoke($this->fallback, $message);

        return true;
    }

    private function routeByPattern(PatternTable $table, string $value, stdClass $payload): bool
    {
        $route = $table->find($value);

        if ($route !== null) {
            $this->invoke($route[0], $payload, $route[1]);
        }

        return $route !== null;
    }

    private function runUpdateHandlers(string $type, stdClass $payload, stdClass $update): bool
    {
        $handlers = $this->updateHandlers[$type] ?? [];

        foreach ($handlers as $handler) {
            $this->invoke($handler, $payload, $update);
        }

        return $handlers !== [];
    }

    private function invoke(callable $handler, stdClass $payload, mixed ...$extra): void
    {
        if ($this->bot !== null) {
            $handler($payload, $this->bot, ...$extra);
        } else {
            $handler($payload, ...$extra);
        }
    }
}
