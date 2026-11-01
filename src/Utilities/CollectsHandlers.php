<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\PluginInterface;

/**
 * Handler registration of BotBuilder: collected here, replayed on the Bot by build()
 */
trait CollectsHandlers
{
    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $commands = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $callbacks = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $texts = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $inlineQueries = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $states = [];

    /** @var array<int, array{0: string, 1: callable}> */
    private array $updates = [];

    /** @var callable|null */
    private $fallback = null;

    /** @var callable[] */
    private array $middleware = [];

    /** @var array<string, callable[]> */
    private array $events = [];

    /** @var PluginInterface[] */
    private array $plugins = [];

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

    public function addText(string $pattern, callable $handler): self
    {
        $this->texts[$pattern] = $handler;
        return $this;
    }

    public function addInlineQuery(string $pattern, callable $handler): self
    {
        $this->inlineQueries[$pattern] = $handler;
        return $this;
    }

    public function addState(string $state, callable $handler): self
    {
        $this->states[$state] = $handler;
        return $this;
    }

    public function addUpdateHandler(string $type, callable $handler): self
    {
        $this->updates[] = [$type, $handler];
        return $this;
    }

    public function setFallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function addMiddleware(callable $handler): self
    {
        $this->middleware[] = $handler;
        return $this;
    }

    public function addEventListener(string $event, callable $handler): self
    {
        $this->events[$event][] = $handler;
        return $this;
    }

    public function addPlugin(PluginInterface $plugin): self
    {
        $this->plugins[] = $plugin;
        return $this;
    }

    private function registerHandlers(Bot $bot): void
    {
        $this->registerKeyed($bot);
        $this->registerUpdates($bot);
        $this->registerEvents($bot);
        $this->registerExtras($bot);
    }

    private function registerKeyed(Bot $bot): void
    {
        foreach ($this->commands as $command => $handler) {
            $bot->command((string) $command, $handler);
        }

        foreach ($this->callbacks as $data => $handler) {
            $bot->callback((string) $data, $handler);
        }

        foreach ($this->texts as $pattern => $handler) {
            $bot->hears((string) $pattern, $handler);
        }
    }

    private function registerUpdates(Bot $bot): void
    {
        foreach ($this->inlineQueries as $pattern => $handler) {
            $bot->inlineQuery((string) $pattern, $handler);
        }

        foreach ($this->states as $state => $handler) {
            $bot->state((string) $state, $handler);
        }

        foreach ($this->updates as [$type, $handler]) {
            $bot->onUpdate($type, $handler);
        }
    }

    private function registerEvents(Bot $bot): void
    {
        foreach ($this->events as $event => $handlers) {
            foreach ($handlers as $handler) {
                $bot->on($event, $handler);
            }
        }
    }

    private function registerExtras(Bot $bot): void
    {
        if ($this->fallback !== null) {
            $bot->fallback($this->fallback);
        }

        foreach ($this->middleware as $handler) {
            $bot->middleware($handler);
        }

        foreach ($this->plugins as $plugin) {
            $bot->plugin($plugin);
        }
    }
}
