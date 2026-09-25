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
     * Run the pipeline and, unless a middleware stops it, the final handler
     *
     * @param callable(): void $core
     * @param array<int, mixed> $arguments Extra arguments passed after the update (e.g. the Bot)
     * @return bool Whether the final handler was reached
     */
    public function process(stdClass $update, callable $core, array $arguments = []): bool
    {
        $reached = false;

        $next = function (int $index) use (&$next, &$reached, $update, $core, $arguments): void {
            if (!isset($this->middleware[$index])) {
                $reached = true;
                $core();
                return;
            }

            $middleware = $this->middleware[$index];
            $proceed = static fn() => $next($index + 1);

            if (self::acceptsNext($middleware, count($arguments))) {
                $middleware($update, ...[...$arguments, $proceed]);
                return;
            }

            if ($middleware($update, ...$arguments) !== false) {
                $proceed();
            }
        };

        $next(0);

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
     * @return callable[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    private static function acceptsNext(callable $middleware, int $argumentCount): bool
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($middleware));

        return $reflection->getNumberOfParameters() > $argumentCount + 1 && !$reflection->isVariadic();
    }
}
