<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Routing\MessageRoutes;
use TGbotPHP\Framework\Routing\PatternTable;
use TGbotPHP\Framework\Routing\Route;
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
    private readonly MessageRoutes $messages;
    private readonly PatternTable $callbacks;
    private readonly PatternTable $inlineQueries;

    /** @var array<string, list<callable>> */
    private array $updateHandlers = [];

    private ?string $botUsername = null;

    private ?Bot $bot = null;

    public function __construct()
    {
        $this->messages = new MessageRoutes();
        $this->callbacks = new PatternTable();
        $this->inlineQueries = new PatternTable();
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
        $this->messages->addCommand($command, $handler);
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
        $this->messages->addText($pattern, $handler);
    }

    /**
     * Handler for messages received while a conversation is in the given state
     */
    public function registerState(string $state, callable $handler): void
    {
        $this->messages->addState($state, $handler);
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
        $this->messages->setFallback($handler);
    }

    public function setUnknownCommandHandler(callable $handler): void
    {
        $this->messages->setUnknownCommand($handler);
    }

    /**
     * @return list<string> Registered command names
     */
    public function getCommands(): array
    {
        return $this->messages->commandNames();
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
     * @param array<string, mixed> $stateData
     */
    public function handleMessage(stdClass $message, ?string $state = null, array $stateData = []): bool
    {
        $route = $this->messages->resolve($message, $state, $stateData, $this->botUsername);

        return $route === false || $this->run($route, $message);
    }

    public function handleCallback(stdClass $callback): bool
    {
        return $this->run($this->callbacks->find(Value::string(Value::path($callback, 'data'))), $callback);
    }

    public function handleInlineQuery(stdClass $query): bool
    {
        return $this->run($this->inlineQueries->find(Value::string(Value::path($query, 'query'))), $query);
    }

    private function runUpdateHandlers(string $type, stdClass $payload, stdClass $update): bool
    {
        $handlers = $this->updateHandlers[$type] ?? [];

        foreach ($handlers as $handler) {
            $this->run(new Route($handler, [$update]), $payload);
        }

        return $handlers !== [];
    }

    /**
     * @return bool Whether a handler ran
     */
    private function run(?Route $route, stdClass $payload): bool
    {
        if ($route === null) {
            return false;
        }

        $arguments = $this->bot !== null ? [$payload, $this->bot, ...$route->arguments] : [$payload, ...$route->arguments];
        ($route->handler)(...$arguments);

        return true;
    }
}
