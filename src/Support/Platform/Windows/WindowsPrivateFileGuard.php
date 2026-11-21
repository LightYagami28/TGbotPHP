<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\Windows;

use Override;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * Windows: NTFS ACLs via icacls. chmod() is a no-op on NTFS, so owner-only
 * access and read-only directories are enforced with real ACLs.
 */
final class WindowsPrivateFileGuard implements PrivateFileGuard
{
    private string $user;

    public function __construct()
    {
        $domain = getenv('USERDOMAIN');
        $name = getenv('USERNAME');
        $this->user = ($domain !== false && $domain !== '' ? $domain . '\\' : '') . ($name !== false ? $name : '');
    }

    #[Override]
    public function restrict(string $path): bool
    {
        if ($this->user === '') {
            return false;
        }

        exec(sprintf('icacls "%s" /inheritance:r /grant:r "%s:(F)"', str_replace('/', '\\', $path), $this->user), $out, $code);

        return $code === 0;
    }

    #[Override]
    public function isRestricted(string $path): bool
    {
        if ($this->user === '') {
            return false;
        }

        exec(sprintf('icacls "%s"', str_replace('/', '\\', $path)), $out, $code);

        if ($code !== 0) {
            return false;
        }

        $acl = implode("\n", $out);
        $granted = str_contains($acl, $this->user . ':(F)') || str_contains($acl, $this->user . ':(M)');
        $others = preg_match('/BUILTIN\\\\Users|Everyone|Authenticated Users|NT AUTHORITY\\\\Authenticated Users/i', $acl) === 1;

        return $granted && !$others;
    }

    #[Override]
    public function makeReadOnly(string $directory): bool
    {
        if ($this->user === '') {
            return false;
        }

        // Deny write/delete to the current user; keep read+execute.
        exec(sprintf('icacls "%s" /deny "%s:(W,D)"', str_replace('/', '\\', $directory), $this->user), $out, $code);

        return $code === 0;
    }

    #[Override]
    public function restoreWritable(string $directory): bool
    {
        if ($this->user === '') {
            return false;
        }

        exec(sprintf('icacls "%s" /remove:d "%s"', str_replace('/', '\\', $directory), $this->user), $out, $code);

        return $code === 0;
    }

    #[Override]
    public function describe(): string
    {
        return 'Windows NTFS ACL (icacls owner-only)';
    }
}
