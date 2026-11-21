<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform;

/**
 * Cross-platform contract for owner-only files and read-only directories.
 *
 * POSIX (Linux/macOS) uses modes 0600 and 0500. Windows uses NTFS ACLs via
 * icacls, because chmod() is a no-op on NTFS.
 */
interface PrivateFileGuard
{
    /** Restrict $path to the current owner after creation. */
    public function restrict(string $path): bool;

    /** True when $path is restricted to the current owner. */
    public function isRestricted(string $path): bool;

    /** Make $directory non-writable (POSIX 0500, owner-only / Windows icacls deny). */
    public function makeReadOnly(string $directory): bool;

    /** Restore $directory to writable (POSIX 0700 / Windows icacls grant). */
    public function restoreWritable(string $directory): bool;

    /** Human-readable description of the enforcement mechanism. */
    public function describe(): string;
}
