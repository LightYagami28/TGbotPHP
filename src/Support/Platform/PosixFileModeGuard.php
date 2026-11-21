<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform;

use TGbotPHP\Support\GuardedCall;

/**
 * Owner-only POSIX modes shared by the Linux and macOS guards: both enforce
 * the same chmod() policy, so the implementation lives in one place.
 */
trait PosixFileModeGuard
{
    /** Restrict $path to owner read/write (0600). */
    public function restrict(string $path): bool
    {
        return GuardedCall::run(static fn(): bool => chmod($path, 0600)) === true;
    }

    /** True when $path is owner-only (0600). */
    public function isRestricted(string $path): bool
    {
        $perms = GuardedCall::run(static fn(): int|false => fileperms($path));

        return is_int($perms) && substr(sprintf('%o', $perms), -4) === '0600';
    }

    /**
     * Revoke write access from a directory without granting it to group or others.
     *
     * 0500 keeps owner read+execute (needed to list and traverse the directory)
     * and gives group and others no access at all. 0555 would expose the same
     * directory to every user on the host. is_dir() keeps the mode restricted
     * to directories, never files or executables.
     */
    public function makeReadOnly(string $directory): bool
    {
        return is_dir($directory) && GuardedCall::run(static fn(): bool => chmod($directory, 0500)) === true;
    }

    /** Restore owner-only read/write on a directory (0700). */
    public function restoreWritable(string $directory): bool
    {
        return GuardedCall::run(static fn(): bool => chmod($directory, 0700)) === true;
    }
}
