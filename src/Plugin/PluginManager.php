<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;

class PluginManager
{
    private array $plugins = [];
    private array $hooks = [];

    public function register(string $name, PluginInterface $plugin): void
    {
        if (isset($this->plugins[$name])) {
            throw new \RuntimeException("Plugin '$name' already registered");
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

    public function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = [];
        }

        $this->hooks[$hook][$priority][] = $callback;
    }

    public function executeHook(string $hook, mixed $value = null): mixed
    {
        if (!isset($this->hooks[$hook])) {
            return $value;
        }

        ksort($this->hooks[$hook]);

        foreach ($this->hooks[$hook] as $callbacks) {
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
