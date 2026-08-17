<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;

/**
 * Command and callback routing system
 *
 * Handles message commands, callback queries, and inline queries.
 */
final class Router
{
    /** @var array<string, callable> */
    private array $commands = [];

    /** @var array<string, callable> */
    private array $callbacks = [];

    /** @var array<string, callable> */
    private array $inlineHandlers = [];

    /** @var callable|null */
    private $defaultHandler = null;

    /**
     * Register command handler
     */
    public function registerCommand(string $command, callable $handler): void
    {
        $normalized = $this->normalizeCommand($command);
        $this->commands[$normalized] = $handler;
    }

    /**
     * Register callback handler
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
     * Set default handler
     */
    public function setDefaultHandler(callable $handler): void
    {
        $this->defaultHandler = $handler;
    }

    /**
     * Handle message routing
     */
    public function handleMessage(stdClass $message): void
    {
        if (!isset($message->text)) {
            return;
        }

        $text = $message->text;

        if (str_starts_with($text, '/')) {
            $this->handleCommand($text, $message);
        } elseif ($this->defaultHandler !== null) {
            call_user_func($this->defaultHandler, $message);
        }
    }

    /**
     * Handle callback routing
     */
    public function handleCallback(stdClass $callback): void
    {
        $data = $callback->data ?? '';

        if (isset($this->callbacks[$data])) {
            call_user_func($this->callbacks[$data], $callback);
        }
    }

    /**
     * Handle inline query routing
     */
    public function handleInlineQuery(stdClass $query): void
    {
        $text = $query->query ?? '';

        if (isset($this->inlineHandlers[$text])) {
            call_user_func($this->inlineHandlers[$text], $query);
        }
    }

    /**
     * Handle command parsing and routing
     */
    private function handleCommand(string $text, stdClass $message): void
    {
        $parts = explode(' ', $text, 2);
        $command = $this->normalizeCommand($parts[0]);

        if (isset($this->commands[$command])) {
            call_user_func($this->commands[$command], $message);
        }
    }

    /**
     * Normalize command name
     */
    private function normalizeCommand(string $command): string
    {
        return strtolower(str_replace('@bot', '', $command));
    }

    /**
     * Get all registered commands
     *
     * @return array<string, callable>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get all registered callbacks
     *
     * @return array<string, callable>
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }
}
