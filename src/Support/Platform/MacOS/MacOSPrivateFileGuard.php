<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\MacOS;

use Override;
use TGbotPHP\Support\GuardedCall;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * macOS: POSIX-compliant, 0600 owner-only and 0500 read-only enforced by the
 * kernel. Kept as a distinct implementation for the macOS target.
 */
final class MacOSPrivateFileGuard implements PrivateFileGuard
{
    #[Override]
    public function restrict(string $path): bool
    {
        return GuardedCall::run(static fn(): bool => chmod($path, 0600)) === true;
    }

    #[Override]
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
    #[Override]
    public function makeReadOnly(string $directory): bool
    {
        return is_dir($directory) && GuardedCall::run(static fn(): bool => chmod($directory, 0500)) === true;
    }

    #[Override]
    public function restoreWritable(string $directory): bool
    {
        return GuardedCall::run(static fn(): bool => chmod($directory, 0700)) === true;
    }

    #[Override]
    public function describe(): string
    {
        return 'macOS POSIX mode 0600 / 0500';
    }
}
