<?php

declare(strict_types=1);

namespace TGbotPHP\Support\Platform\Linux;

use Override;
use TGbotPHP\Support\Platform\PosixFileModeGuard;
use TGbotPHP\Support\Platform\PrivateFileGuard;

/**
 * Linux: POSIX owner-only mode 0600, read-only directory mode 0500.
 */
final class LinuxPrivateFileGuard implements PrivateFileGuard
{
    use PosixFileModeGuard;

    #[Override]
    public function describe(): string
    {
        return 'Linux POSIX mode 0600 / 0500';
    }
}
