<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

/**
 * Creates a file that is readable by its owner only
 */
final class PrivateFile
{
    private function __construct()
    {
        // Static helpers only
    }

    /**
     * Create an empty file with mode 0600.
     *
     * An existing file is left untouched. Stream wrappers (php://, file://)
     * are ignored: the caller writes those itself. The exclusive create
     * (`fopen` mode `x`) closes the window between "does it exist?" and "create it".
     */
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
            @chmod($path, 0600);
        }
    }
}
