<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;

use TGbotPHP\Exceptions\PluginException;
use Throwable;

/**
 * Plugin registry and priority-ordered hooks
 *
 * activate() runs before the plugin is stored. If it throws, the plugin is
 * not registered and hooks it added are removed. unregister() removes the
 * hooks that plugin added during activate().
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    /**
     * @var array<string, array<int, list<array{callback: callable, owner: ?string}>>>
     */
    private array $hooks = [];

    /** Name of the plugin whose activate() is running, so its hooks can be tracked */
    private ?string $activating = null;

    public function register(string $name, PluginInterface $plugin): void
    {
        if (isset($this->plugins[$name])) {
            throw new PluginException("Plugin '$name' already registered");
        }

        $this->activating = $name;

        try {
            $plugin->activate();
        } catch (Throwable $e) {
            $this->dropOwnedHooks($name);
            throw $e;
        } finally {
            $this->activating = null;
        }

        $this->plugins[$name] = $plugin;
    }

    public function unregister(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            return;
        }

        $this->plugins[$name]->deactivate();
        $this->dropOwnedHooks($name);
        unset($this->plugins[$name]);
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
     * Add a hook callback; lower priorities run first.
     * A hook added while a plugin is activating is removed with that plugin.
     */
    public function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][$priority][] = [
            'callback' => $callback,
            'owner' => $this->activating,
        ];
        ksort($this->hooks[$hook]);
    }

    /**
     * Pass a value through every callback of a hook
     */
    public function executeHook(string $hook, mixed $value = null): mixed
    {
        foreach ($this->hooks[$hook] ?? [] as $callbacks) {
            foreach ($callbacks as $entry) {
                $value = ($entry['callback'])($value);
            }
        }

        return $value;
    }

    public function isActive(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    private function dropOwnedHooks(string $name): void
    {
        foreach ($this->hooks as $hook => $priorities) {
            foreach ($priorities as $priority => $callbacks) {
                $kept = [];

                foreach ($callbacks as $entry) {
                    if ($entry['owner'] !== $name) {
                        $kept[] = $entry;
                    }
                }

                if ($kept === []) {
                    unset($this->hooks[$hook][$priority]);
                } else {
                    $this->hooks[$hook][$priority] = $kept;
                }
            }

            if ($this->hooks[$hook] === []) {
                unset($this->hooks[$hook]);
            }
        }
    }
}
