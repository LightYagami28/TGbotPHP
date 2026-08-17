<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

/**
 * Event dispatcher for custom events
 *
 * Allows plugins and handlers to hook into bot lifecycle.
 */
final class EventDispatcher
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    /**
     * Listen to event
     */
    public function listen(string $event, callable $handler): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $handler;
    }

    /**
     * Dispatch event
     */
    public function dispatch(string $event, mixed $data = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $handler) {
            call_user_func($handler, $data);
        }
    }

    /**
     * Get listeners for event
     *
     * @return callable[]
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Remove all listeners
     */
    public function clear(): void
    {
        $this->listeners = [];
    }
}
