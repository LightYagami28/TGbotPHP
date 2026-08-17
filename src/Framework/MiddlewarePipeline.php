<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;

/**
 * Middleware pipeline for processing updates
 *
 * Executes middleware in FIFO order before routing.
 */
final class MiddlewarePipeline
{
    /** @var callable[] */
    private array $middleware = [];

    /**
     * Add middleware to pipeline
     */
    public function add(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Execute middleware pipeline
     */
    public function execute(stdClass $update): void
    {
        foreach ($this->middleware as $middleware) {
            call_user_func($middleware, $update);
        }
    }

    /**
     * Get all middleware
     *
     * @return callable[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
