<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\Linux;

use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * Linux: POSIX owner-only mode 0600, read-only directory mode 0555.
 */
final class LinuxPrivateFileGuard implements PrivateFileGuard
{
    public function restrict(string $path): bool
    {
        return @chmod($path, 0600);
    }

    public function isRestricted(string $path): bool
    {
        $perms = @fileperms($path);

        return $perms !== false && substr(sprintf('%o', $perms), -4) === '0600';
    }

    public function makeReadOnly(string $directory): bool
    {
        return @chmod($directory, 0555);
    }

    public function restoreWritable(string $directory): bool
    {
        return @chmod($directory, 0700);
    }

    public function describe(): string
    {
        return 'Linux POSIX mode 0600 / 0555';
    }
}
