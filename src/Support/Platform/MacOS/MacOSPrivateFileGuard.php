<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\MacOS;

use Override;
use TGbotPHP\Support\Platform\PosixFileModeGuard;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * macOS: POSIX-compliant, 0600 owner-only and 0500 read-only enforced by the
 * kernel. Kept as a distinct implementation for the macOS target.
 */
final class MacOSPrivateFileGuard implements PrivateFileGuard
{
    use PosixFileModeGuard;

    #[Override]
    public function describe(): string
    {
        return 'macOS POSIX mode 0600 / 0500';
    }
}
