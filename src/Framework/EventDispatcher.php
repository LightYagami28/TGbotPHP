<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

/**
 * Event dispatcher for custom events
 *
 * Allows plugins and handlers to hook into bot lifecycle.
 *
 * Built-in events: "update.received", "update.processed", "error", "error.api",
 * "polling.started", "polling.stopped".
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
        $this->listeners[$event][] = $handler;
    }

    /**
     * Dispatch event
     */
    public function dispatch(string $event, mixed ...$data): void
    {
        foreach ($this->listeners[$event] ?? [] as $handler) {
            $handler(...$data);
        }
    }

    /**
     * Check whether an event has listeners
     */
    public function hasListeners(string $event): bool
    {
        return ($this->listeners[$event] ?? []) !== [];
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
     * Remove listeners of one event, or all listeners
     */
    public function clear(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
        } else {
            unset($this->listeners[$event]);
        }
    }
}
