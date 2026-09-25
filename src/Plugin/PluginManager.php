<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;
use TGbotPHP\Exceptions\PluginException;

/**
 * Plugin registry and priority-ordered hooks
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    /** @var array<string, array<int, callable[]>> */
    private array $hooks = [];

    public function register(string $name, PluginInterface $plugin): void
    {
        if (isset($this->plugins[$name])) {
            throw new PluginException("Plugin '$name' already registered");
        }

        $this->plugins[$name] = $plugin;
        $plugin->activate();
    }

    public function unregister(string $name): void
    {
        if (isset($this->plugins[$name])) {
            $this->plugins[$name]->deactivate();
            unset($this->plugins[$name]);
        }
    }

    public function get(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * @return array<string, PluginInterface>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * Add a hook callback; lower priorities run first
     */
    public function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][$priority][] = $callback;
        ksort($this->hooks[$hook]);
    }

    /**
     * Pass a value through every callback of a hook
     */
    public function executeHook(string $hook, mixed $value = null): mixed
    {
        foreach ($this->hooks[$hook] ?? [] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value);
            }
        }

        return $value;
    }

    public function isActive(string $name): bool
    {
        return isset($this->plugins[$name]);
    }
}
