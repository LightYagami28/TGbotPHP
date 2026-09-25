<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Support\Value;

/**
 * Command, text, callback, inline query and update routing
 *
 * Patterns used by callbacks, inline queries and hears() can be:
 * - an exact string:            'menu'
 * - a wildcard pattern:         'page:*'          (captures what "*" matched)
 * - a regular expression:       '/^page:(\d+)$/'  (delimiters / # or ~)
 *
 * Handlers receive the update payload first, then the Bot (when the router
 * is driven by a Bot) and route specific data:
 *
 *     command:  fn(stdClass $message, Bot $bot, string $args)
 *     hears:    fn(stdClass $message, Bot $bot, array $matches)
 *     callback: fn(stdClass $callbackQuery, Bot $bot, array $matches)
 *     inline:   fn(stdClass $inlineQuery, Bot $bot, array $matches)
 *     state:    fn(stdClass $message, Bot $bot, array $stateData)
 *     update:   fn(stdClass $payload, Bot $bot, stdClass $update)
 *     default:  fn(stdClass $message, Bot $bot)
 */
final class Router
{
    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $commands = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $callbacks = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $inlineHandlers = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $textHandlers = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $stateHandlers = [];

    /** @var array<string, callable[]> */
    private array $updateHandlers = [];

    /** @var callable|null */
    private $defaultHandler = null;

    /** @var callable|null */
    private $unknownCommandHandler = null;

    private ?string $botUsername = null;

    private ?Bot $bot = null;

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
     * Register command handler ("start", "/start" and "START" are equivalent)
     */
    public function registerCommand(string $command, callable $handler): void
    {
        $this->commands[$this->normalizeCommand($command)] = $handler;
    }

    /**
     * Register callback query handler
     */
    public function registerCallback(string $data, callable $handler): void
    {
        $this->callbacks[$data] = $handler;
    }

    /**
     * Register inline query handler
     */
    public function registerInlineQuery(string $query, callable $handler): void
    {
        $this->inlineHandlers[$query] = $handler;
    }

    /**
     * Register handler for text messages (or media captions) matching a pattern
     */
    public function registerText(string $pattern, callable $handler): void
    {
        $this->textHandlers[$pattern] = $handler;
    }

    /**
     * Register handler for messages received while a conversation is in the given state
     */
    public function registerState(string $state, callable $handler): void
    {
        $this->stateHandlers[$state] = $handler;
    }

    /**
     * Register handler for an update type ("message", "chat_member", "pre_checkout_query", ...)
     *
     * Type handlers run when no more specific route handled the update.
     */
    public function registerUpdate(string $type, callable $handler): void
    {
        $this->updateHandlers[$type][] = $handler;
    }

    /**
     * Set handler for text messages that match no other route
     */
    public function setDefaultHandler(callable $handler): void
    {
        $this->defaultHandler = $handler;
    }

    /**
     * Set handler for commands that are not registered
     */
    public function setUnknownCommandHandler(callable $handler): void
    {
        $this->unknownCommandHandler = $handler;
    }

    /**
     * Route a complete update
     *
     * @param array<string, mixed> $stateData
     * @return bool Whether a handler was found
     */
    public function dispatch(stdClass $update, ?string $state = null, array $stateData = []): bool
    {
        $type = UpdateParser::getType($update);

        if ($type === null) {
            return false;
        }

        $payload = Value::object($update->$type);

        if ($payload === null) {
            return false;
        }

        $handled = match ($type) {
            'message' => $this->handleMessage($payload, $state, $stateData),
            'callback_query' => $this->handleCallback($payload),
            'inline_query' => $this->handleInlineQuery($payload),
            default => false,
        };

        if ($handled || !isset($this->updateHandlers[$type])) {
            return $handled;
        }

        foreach ($this->updateHandlers[$type] as $handler) {
            $this->invoke($handler, $payload, $update);
        }

        return true;
    }

    /**
     * Handle message routing
     *
     * Order: commands, conversation state, text patterns, default handler.
     *
     * @param array<string, mixed> $stateData
     */
    public function handleMessage(stdClass $message, ?string $state = null, array $stateData = []): bool
    {
        $text = isset($message->text) && is_string($message->text) ? $message->text : null;

        if ($text !== null && str_starts_with($text, '/')) {
            $result = $this->handleCommand($text, $message);
            if ($result !== null) {
                return $result;
            }
        }

        if ($state !== null && isset($this->stateHandlers[$state])) {
            $this->invoke($this->stateHandlers[$state], $message, $stateData);
            return true;
        }

        $content = $text ?? (isset($message->caption) && is_string($message->caption) ? $message->caption : null);

        if ($content !== null) {
            foreach ($this->textHandlers as $pattern => $handler) {
                $matches = self::match((string) $pattern, $content);
                if ($matches !== null) {
                    $this->invoke($handler, $message, $matches);
                    return true;
                }
            }
        }

        if ($text !== null && $this->defaultHandler !== null) {
            $this->invoke($this->defaultHandler, $message);
            return true;
        }

        return false;
    }

    /**
     * Handle callback routing
     */
    public function handleCallback(stdClass $callback): bool
    {
        $data = Value::string(Value::path($callback, 'data'));

        return $this->routeByPattern($this->callbacks, $data, $callback);
    }

    /**
     * Handle inline query routing
     */
    public function handleInlineQuery(stdClass $query): bool
    {
        $text = Value::string(Value::path($query, 'query'));

        return $this->routeByPattern($this->inlineHandlers, $text, $query);
    }

    /**
     * Match a value against an exact, wildcard or regex pattern
     *
     * @return array<int|string, string>|null Captured groups (index 0 is the full match), or null
     */
    public static function match(string $pattern, string $value): ?array
    {
        if (self::isRegex($pattern)) {
            return preg_match($pattern, $value, $matches) === 1 ? $matches : null;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '(.*)', preg_quote($pattern, '/')) . '$/su';

            return preg_match($regex, $value, $matches) === 1 ? $matches : null;
        }

        return $pattern === $value ? [$value] : null;
    }

    /**
     * Parse a command message
     *
     * @return array{command: string, username: ?string, args: string}|null
     */
    public static function parseCommand(string $text): ?array
    {
        if (preg_match('/^\/([A-Za-z0-9_]{1,64})(?:@([A-Za-z0-9_]{3,}))?(?:\s+(.*))?$/su', $text, $matches) !== 1) {
            return null;
        }

        return [
            'command' => strtolower($matches[1]),
            'username' => ($matches[2] ?? '') !== '' ? strtolower($matches[2]) : null,
            'args' => trim($matches[3] ?? ''),
        ];
    }

    /**
     * @param array<int|string, callable> $handlers
     */
    private function routeByPattern(array $handlers, string $value, stdClass $payload): bool
    {
        if (isset($handlers[$value])) {
            $this->invoke($handlers[$value], $payload, [$value]);
            return true;
        }

        foreach ($handlers as $pattern => $handler) {
            $pattern = (string) $pattern;
            if (!self::isRegex($pattern) && !str_contains($pattern, '*')) {
                continue;
            }

            $matches = self::match($pattern, $value);
            if ($matches !== null) {
                $this->invoke($handler, $payload, $matches);
                return true;
            }
        }

        return false;
    }

    /**
     * Handle command parsing and routing
     *
     * @return bool|null null when the message should continue through the other routes
     */
    private function handleCommand(string $text, stdClass $message): ?bool
    {
        $parsed = self::parseCommand($text);

        if ($parsed === null) {
            return null;
        }

        // Command explicitly addressed to another bot
        if ($parsed['username'] !== null && $this->botUsername !== null && $parsed['username'] !== $this->botUsername) {
            return false;
        }

        if (isset($this->commands[$parsed['command']])) {
            $this->invoke($this->commands[$parsed['command']], $message, $parsed['args']);
            return true;
        }

        if ($this->unknownCommandHandler !== null) {
            $this->invoke($this->unknownCommandHandler, $message, $parsed['args']);
            return true;
        }

        return null;
    }

    private function invoke(callable $handler, stdClass $payload, mixed ...$extra): void
    {
        if ($this->bot !== null) {
            $handler($payload, $this->bot, ...$extra);
            return;
        }

        $handler($payload, ...$extra);
    }

    private static function isRegex(string $pattern): bool
    {
        if (strlen($pattern) < 3 || !in_array($pattern[0], ['/', '#', '~'], true)) {
            return false;
        }

        $end = strrpos($pattern, $pattern[0]);
        if ($end === false || $end === 0 || preg_match('/^[imsxuUXJD]*$/', substr($pattern, $end + 1)) !== 1) {
            return false;
        }

        return @preg_match($pattern, '') !== false;
    }

    /**
     * Normalize command name
     */
    private function normalizeCommand(string $command): string
    {
        $command = strtolower(ltrim(trim($command), '/'));
        $at = strpos($command, '@');

        return $at === false ? $command : substr($command, 0, $at);
    }

    /**
     * Get all registered commands
     *
     * @return array<int|string, callable>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get all registered callbacks
     *
     * @return array<int|string, callable>
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }
}
