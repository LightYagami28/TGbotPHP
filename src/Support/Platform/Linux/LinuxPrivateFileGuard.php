<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\Linux;

use Override;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * Linux: POSIX owner-only mode 0600, read-only directory mode 0500.
 */
final class LinuxPrivateFileGuard implements PrivateFileGuard
{
    #[Override]
    public function restrict(string $path): bool
    {
        return @chmod($path, 0600);
    }

    #[Override]
    public function isRestricted(string $path): bool
    {
        $perms = @fileperms($path);

        return $perms !== false && substr(sprintf('%o', $perms), -4) === '0600';
    }

    /**
     * Revoke write access from a directory without granting it to group or others.
     *
     * 0500 keeps owner read+execute (needed to list and traverse the directory)
     * and gives group and others no access at all. 0555 would expose the same
     * directory to every user on the host. is_dir() keeps the mode restricted
     * to directories, never files or executables.
     */
    #[Override]
    public function makeReadOnly(string $directory): bool
    {
        return is_dir($directory) && @chmod($directory, 0500);
    }

    #[Override]
    public function restoreWritable(string $directory): bool
    {
        return @chmod($directory, 0700);
    }

    #[Override]
    public function describe(): string
    {
        return 'Linux POSIX mode 0600 / 0500';
    }
}
