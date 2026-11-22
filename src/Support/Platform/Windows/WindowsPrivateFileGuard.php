<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\Windows;

use Override;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * Windows: NTFS ACLs via icacls. chmod() is a no-op on NTFS, so owner-only
 * access and read-only directories are enforced with real ACLs.
 *
 * The command line is a constant: the validated path and user are passed in
 * environment variables, so no part of a file name can be interpreted by the
 * shell as a command or as an extra switch.
 */
final class WindowsPrivateFileGuard implements PrivateFileGuard
{
    /** Environment variables used to hand the arguments to icacls. */
    private const string FILE_ENV = 'TGBOTPHP_ACL_FILE';
    private const string USER_ENV = 'TGBOTPHP_ACL_USER';

    private string $user;
    private bool $windows;
    private string|false $previousFile = false;
    private string|false $previousUser = false;

    /**
     * @param bool|null $windows Command syntax to use; null detects the host OS.
     *                           Injectable so both syntaxes are testable on any CI runner.
     */
    public function __construct(?bool $windows = null)
    {
        $domain = getenv('USERDOMAIN');
        $name = getenv('USERNAME');
        $this->user = ($domain !== false && $domain !== '' ? $domain . '\\' : '') . ($name !== false ? $name : '');
        $this->windows = $windows ?? (PHP_OS_FAMILY === 'Windows');
    }

    #[Override]
    public function restrict(string $path): bool
    {
        if (!$this->prepare($path)) {
            return false;
        }

        $out = [];
        $code = 0;

        try {
            if ($this->windows) {
                exec('icacls "%TGBOTPHP_ACL_FILE%" /inheritance:r /grant:r "%TGBOTPHP_ACL_USER%:(F)"', $out, $code);
            } else {
                exec('icacls "$TGBOTPHP_ACL_FILE" /inheritance:r /grant:r "$TGBOTPHP_ACL_USER:(F)"', $out, $code);
            }
        } finally {
            $this->restore();
        }

        return $code === 0;
    }

    #[Override]
    public function isRestricted(string $path): bool
    {
        if (!$this->prepare($path)) {
            return false;
        }

        $out = [];
        $code = 0;

        try {
            if ($this->windows) {
                exec('icacls "%TGBOTPHP_ACL_FILE%"', $out, $code);
            } else {
                exec('icacls "$TGBOTPHP_ACL_FILE"', $out, $code);
            }
        } finally {
            $this->restore();
        }

        if ($code !== 0) {
            return false;
        }

        $acl = implode("\n", $out);
        $granted = str_contains($acl, $this->user . ':(F)') || str_contains($acl, $this->user . ':(M)');
        $others = preg_match(
            '/BUILTIN\\\\Users|Everyone|Authenticated Users|NT AUTHORITY\\\\Authenticated Users/i',
            $acl,
        ) === 1;

        return $granted && !$others;
    }

    #[Override]
    public function makeReadOnly(string $directory): bool
    {
        if (!$this->prepare($directory)) {
            return false;
        }

        $out = [];
        $code = 0;

        try {
            // Deny write/delete to the current user; keep read+execute.
            if ($this->windows) {
                exec('icacls "%TGBOTPHP_ACL_FILE%" /deny "%TGBOTPHP_ACL_USER%:(W,D)"', $out, $code);
            } else {
                exec('icacls "$TGBOTPHP_ACL_FILE" /deny "$TGBOTPHP_ACL_USER:(W,D)"', $out, $code);
            }
        } finally {
            $this->restore();
        }

        return $code === 0;
    }

    #[Override]
    public function restoreWritable(string $directory): bool
    {
        if (!$this->prepare($directory)) {
            return false;
        }

        $out = [];
        $code = 0;

        try {
            if ($this->windows) {
                exec('icacls "%TGBOTPHP_ACL_FILE%" /remove:d "%TGBOTPHP_ACL_USER%"', $out, $code);
            } else {
                exec('icacls "$TGBOTPHP_ACL_FILE" /remove:d "$TGBOTPHP_ACL_USER"', $out, $code);
            }
        } finally {
            $this->restore();
        }

        return $code === 0;
    }

    #[Override]
    public function describe(): string
    {
        return 'Windows NTFS ACL (icacls owner-only)';
    }

    /** Validate the arguments and publish them to the constant command line. */
    private function prepare(string $path): bool
    {
        if (
            $this->user === ''
            || !self::isSafePath($path)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*(?:\\\\[A-Za-z0-9][A-Za-z0-9._-]*)?\z/', $this->user) !== 1
        ) {
            return false;
        }

        $this->previousFile = getenv(self::FILE_ENV);
        $this->previousUser = getenv(self::USER_ENV);
        putenv(self::FILE_ENV . '=' . str_replace('/', '\\', $path));
        putenv(self::USER_ENV . '=' . $this->user);

        return true;
    }

    /** Put the previous environment back, including unset variables. */
    private function restore(): void
    {
        putenv(self::FILE_ENV . ($this->previousFile === false ? '' : '=' . $this->previousFile));
        putenv(self::USER_ENV . ($this->previousUser === false ? '' : '=' . $this->previousUser));
        $this->previousFile = false;
        $this->previousUser = false;
    }

    /** Absolute path without characters the shell could reinterpret. */
    private static function isSafePath(string $path): bool
    {
        if ($path === '' || preg_match('/[\x00-\x1F\x7F"&|<>\^%!$\'`]/', $path) === 1) {
            return false;
        }

        return preg_match('/\A(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path) === 1;
    }
}
