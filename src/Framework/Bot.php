<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use TGbotPHP\Core\Config;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Exceptions\InvalidTokenException;
use TGbotPHP\Exceptions\ApiException;
use stdClass;

/**
 * Main Bot class - orchestrates all Telegram Bot API interactions
 *
 * Provides high-level API for handling updates, routing, middleware, and events.
 */
final class Bot extends ApiClient
{
    private readonly Router $router;
    private readonly MiddlewarePipeline $middleware;
    private readonly EventDispatcher $events;
    private ?stdClass $update = null;

    public function __construct(
        string $token,
        bool $debug = false,
        string|false $debugFile = false,
        string|false $secretToken = false
    ) {
        try {
            $config = new Config($token, $debug, $debugFile, $secretToken);
            parent::__construct($config);

            $this->router = new Router();
            $this->middleware = new MiddlewarePipeline();
            $this->events = new EventDispatcher();
        } catch (\InvalidArgumentException $e) {
            throw new InvalidTokenException($e->getMessage());
        }
    }

    /**
     * Handle incoming webhook update
     */
    public function handleUpdate(string $webhookJson): void
    {
        try {
            $this->update = UpdateParser::parse($webhookJson);
            $this->middleware->execute($this->update);
            $this->events->dispatch('update.received', $this->update);

            if (UpdateParser::hasMessage($this->update)) {
                $this->router->handleMessage($this->update->message);
            } elseif (UpdateParser::hasCallbackQuery($this->update)) {
                $this->router->handleCallback($this->update->callback_query);
            } elseif (UpdateParser::hasInlineQuery($this->update)) {
                $this->router->handleInlineQuery($this->update->inline_query);
            }

            $this->events->dispatch('update.processed', $this->update);
        } catch (ApiException $e) {
            $this->events->dispatch('error.api', $e);
            throw $e;
        }
    }

    /**
     * Register command handler
     */
    public function command(string $command, callable $handler): void
    {
        $this->router->registerCommand($command, $handler);
    }

    /**
     * Register callback handler
     */
    public function callback(string $data, callable $handler): void
    {
        $this->router->registerCallback($data, $handler);
    }

    /**
     * Register middleware
     */
    public function middleware(callable $handler): void
    {
        $this->middleware->add($handler);
    }

    /**
     * Listen to events
     */
    public function on(string $event, callable $handler): void
    {
        $this->events->listen($event, $handler);
    }

    /**
     * Get current update
     */
    public function getUpdate(): ?stdClass
    {
        return $this->update;
    }

    /**
     * Get router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get event dispatcher
     */
    public function getEvents(): EventDispatcher
    {
        return $this->events;
    }

    /**
     * Get middleware pipeline
     */
    public function getMiddleware(): MiddlewarePipeline
    {
        return $this->middleware;
    }
}
