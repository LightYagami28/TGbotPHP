<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Concerns;

use TGbotPHP\Framework\Kernel;

/**
 * Fluent handler registration for the Bot
 *
 * See Router for the handler signatures and Routing\Pattern for the patterns.
 */
trait RegistersHandlers
{
    abstract protected function kernel(): Kernel;

    /**
     * fn(stdClass $message, Bot $bot, string $args)
     */
    public function command(string $command, callable $handler): static
    {
        $this->kernel()->router->registerCommand($command, $handler);
        return $this;
    }

    /**
     * fn(stdClass $callbackQuery, Bot $bot, array $matches)
     */
    public function callback(string $pattern, callable $handler): static
    {
        $this->kernel()->router->registerCallback($pattern, $handler);
        return $this;
    }

    /**
     * fn(stdClass $message, Bot $bot, array $matches), matched against the text or the caption
     */
    public function hears(string $pattern, callable $handler): static
    {
        $this->kernel()->router->registerText($pattern, $handler);
        return $this;
    }

    /**
     * fn(stdClass $inlineQuery, Bot $bot, array $matches); "*" matches every query
     */
    public function inlineQuery(string $pattern, callable $handler): static
    {
        $this->kernel()->router->registerInlineQuery($pattern, $handler);
        return $this;
    }

    /**
     * fn(stdClass $payload, Bot $bot, stdClass $update), for any update type
     */
    public function onUpdate(string $type, callable $handler): static
    {
        $this->kernel()->router->registerUpdate($type, $handler);
        return $this;
    }

    /**
     * fn(stdClass $message, Bot $bot), for text messages no other route handled
     */
    public function fallback(callable $handler): static
    {
        $this->kernel()->router->setDefaultHandler($handler);
        return $this;
    }

    /**
     * fn(stdClass $message, Bot $bot, string $args)
     */
    public function onUnknownCommand(callable $handler): static
    {
        $this->kernel()->router->setUnknownCommandHandler($handler);
        return $this;
    }

    /**
     * fn(stdClass $message, Bot $bot, array $stateData), requires useConversations()
     */
    public function state(string $state, callable $handler): static
    {
        $this->kernel()->router->registerState($state, $handler);
        return $this;
    }

    public function middleware(callable $handler): static
    {
        $this->kernel()->middleware->add($handler);
        return $this;
    }

    public function on(string $event, callable $handler): static
    {
        $this->kernel()->events->listen($event, $handler);
        return $this;
    }

    /**
     * fn(Throwable $e, ?stdClass $update, Bot $bot)
     */
    public function onError(callable $handler): static
    {
        return $this->on('error', $handler);
    }
}
