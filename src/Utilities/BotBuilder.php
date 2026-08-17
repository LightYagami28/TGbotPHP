<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use TGbotPHP\Framework\Bot;

class BotBuilder
{
    private string $token;
    private bool $debug = false;
    private string|false $debugFile = false;
    private string|false $secretToken = false;
    private array $commands = [];
    private array $callbacks = [];
    private array $middleware = [];
    private array $events = [];

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function withDebug(string|false $logFile = '/tmp/bot.log'): self
    {
        $this->debug = true;
        $this->debugFile = $logFile;
        return $this;
    }

    public function withSecretToken(string $token): self
    {
        $this->secretToken = $token;
        return $this;
    }

    public function addCommand(string $command, callable $handler): self
    {
        $this->commands[$command] = $handler;
        return $this;
    }

    public function addCallback(string $data, callable $handler): self
    {
        $this->callbacks[$data] = $handler;
        return $this;
    }

    public function addMiddleware(callable $handler): self
    {
        $this->middleware[] = $handler;
        return $this;
    }

    public function addEventListener(string $event, callable $handler): self
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $handler;
        return $this;
    }

    public function build(): Bot
    {
        $bot = new Bot(
            token: $this->token,
            debug: $this->debug,
            debugFile: $this->debugFile,
            secretToken: $this->secretToken
        );

        foreach ($this->commands as $command => $handler) {
            $bot->command($command, $handler);
        }

        foreach ($this->callbacks as $data => $handler) {
            $bot->callback($data, $handler);
        }

        foreach ($this->middleware as $handler) {
            $bot->middleware($handler);
        }

        foreach ($this->events as $event => $handlers) {
            foreach ($handlers as $handler) {
                $bot->on($event, $handler);
            }
        }

        return $bot;
    }
}
