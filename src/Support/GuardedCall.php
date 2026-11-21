<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

use ErrorException;

/**
 * Runs a PHP function that reports failures through warnings, handling those
 * warnings explicitly instead of silencing them with the @ operator.
 *
 * A warning is promoted to ErrorException, logged and handed back to the caller
 * as false. Each call site then decides whether false means "skip", "use a
 * default" or "throw a domain exception", so no failure is hidden.
 */
final class GuardedCall
{
    private function __construct()
    {
        // Static helpers only
    }

    /**
     * @param callable(): mixed $operation Operation that may raise a warning
     *
     * @return mixed The operation result, or false when it raised a warning
     */
    public static function run(callable $operation): mixed
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE,
        );

        try {
            return $operation();
        } catch (ErrorException $exception) {
            error_log('[TGbotPHP] ' . $exception->getMessage());

            return false;
        } finally {
            restore_error_handler();
        }
    }
}
