<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

/**
 * Cross-platform sink for error_log() during tests that intentionally trigger
 * a handled failure. /dev/null does not exist on Windows; NUL works there but
 * not on POSIX. A temp file works on both and is removed at tear-down.
 */
trait NullLogTrait
{
    private static ?string $nullLogPath = null;

    protected static function nullLog(): string
    {
        if (self::$nullLogPath === null) {
            $path = tempnam(sys_get_temp_dir(), 'tgbotphp-null-log-');
            self::$nullLogPath = $path === false
                ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgbotphp-null-log'
                : $path;
        }

        return self::$nullLogPath;
    }

    protected static function cleanNullLog(): void
    {
        if (self::$nullLogPath !== null && is_file(self::$nullLogPath)) {
            @unlink(self::$nullLogPath);
        }
        self::$nullLogPath = null;
    }
}
