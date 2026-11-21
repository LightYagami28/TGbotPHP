<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

use TGbotPHP\Support\Platform\PlatformGuard;

/**
 * Create a file that is readable by its owner only.
 *
 * Cross-platform: mode 0600 on POSIX (Linux/macOS), owner-only NTFS ACL on
 * Windows. The exclusive create (fopen mode `x`) closes the window between
 * "does it exist?" and "create it". Stream wrappers (php://, file://) are
 * ignored: the caller writes those itself.
 */
final class PrivateFile
{
    private function __construct()
    {
        // Static helpers only
    }

    /** Create the file with owner-only permissions when it does not exist yet. */
    public static function create(string $path): void
    {
        if (str_contains($path, '://') || is_file($path)) {
            return;
        }

        $umask = umask(0077);

        try {
            $handle = @fopen($path, 'xb');
        } finally {
            umask($umask);
        }

        if ($handle !== false) {
            fclose($handle);
            PlatformGuard::current()->restrict($path);
        }
    }
}
