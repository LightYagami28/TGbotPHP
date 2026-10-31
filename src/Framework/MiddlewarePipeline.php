<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use Closure;
use ReflectionFunction;
use stdClass;

/**
 * Middleware pipeline for processing updates
 *
 * Two middleware styles are supported:
 *
 *     // Simple: runs before routing; return false to stop processing
 *     $bot->middleware(function (stdClass $update, Bot $bot) {
 *         return !isBanned($update);
 *     });
 *
 *     // Onion: wraps the rest of the pipeline (third parameter is $next)
 *     $bot->middleware(function (stdClass $update, Bot $bot, callable $next) {
 *         $start = microtime(true);
 *         $next();
 *         log(microtime(true) - $start);
 *     });
 */
final class MiddlewarePipeline
{
    /** @var list<Closure> */
    private array $middleware = [];

    /** @var list<int|null> Parameter count of each middleware, null when variadic */
    private array $parameterCounts = [];

    /**
     * Add middleware to pipeline
     */
    public function add(callable $middleware): void
    {
        $closure = Closure::fromCallable($middleware);
        $reflection = new ReflectionFunction($closure);

        $this->middleware[] = $closure;
        // An optional parameter is not $next: only a required one makes the middleware onion-shaped
        $this->parameterCounts[] = $reflection->isVariadic() ? null : $reflection->getNumberOfRequiredParameters();
    }

    /**
     * Run the pipeline and, unless a middleware stops it, the final handler
     *
     * @param callable(): void $core
     * @param array<int, mixed> $arguments Extra arguments passed after the update (e.g. the Bot)
     * @return bool Whether the final handler was reached
     */
    public function process(stdClass $update, callable $core, array $arguments = []): bool
    {
        $reached = false;

        /** @var (Closure(int): void)|null $step */
        $step = null;
        $step = function (int $index) use (&$step, &$reached, $update, $core, $arguments): void {
            if (!isset($this->middleware[$index])) {
                $reached = true;
                $core();
                return;
            }

            $middleware = $this->middleware[$index];
            $proceed = static function () use (&$step, $index): void {
                $step($index + 1);
            };

            // A required parameter after the update and the arguments receives $next
            if (($this->parameterCounts[$index] ?? 0) > count($arguments) + 1) {
                $middleware($update, ...[...$arguments, $proceed]);
                return;
            }

            if ($middleware($update, ...$arguments) !== false) {
                $proceed();
            }
        };

        try {
            $step(0);
        } finally {
            // Drop the self-reference so long polling does not wait for the GC
            $step = null;
        }

        return $reached;
    }

    /**
     * Execute middleware without a final handler
     *
     * @return bool false when a middleware stopped the pipeline
     */
    public function execute(stdClass $update): bool
    {
        return $this->process($update, static function (): void {
            // No final handler: only the middleware runs
        });
    }

    /**
     * @return list<Closure>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
