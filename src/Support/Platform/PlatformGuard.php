<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform;

use TGbotPHP\Support\Platform\Linux\LinuxPrivateFileGuard;
use TGbotPHP\Support\Platform\MacOS\MacOSPrivateFileGuard;
use TGbotPHP\Support\Platform\Windows\WindowsPrivateFileGuard;

/**
 * Selects the platform-specific private-file guard.
 *
 * One API, three implementations (src/Support/Platform/{Linux,MacOS,Windows}).
 * PHP_OS_FAMILY is the only dispatch point; the rest of the library never
 * branches on the OS.
 */
final class PlatformGuard
{
    private static ?PrivateFileGuard $instance = null;

    public static function current(): PrivateFileGuard
    {
        if (self::$instance === null) {
            self::$instance = self::forFamily(PHP_OS_FAMILY);
        }

        return self::$instance;
    }

    /**
     * Build the guard for an explicit OS family.
     *
     * The dispatch is kept injectable so every platform branch can be
     * exercised on a single host; current() still uses PHP_OS_FAMILY.
     */
    public static function forFamily(string $family): PrivateFileGuard
    {
        return match ($family) {
            'Windows' => new WindowsPrivateFileGuard(),
            'Darwin' => new MacOSPrivateFileGuard(),
            default => new LinuxPrivateFileGuard(),
        };
    }

    /** Test helper: inject a specific guard to exercise all three platforms. */
    public static function use(PrivateFileGuard $guard): void
    {
        self::$instance = $guard;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
