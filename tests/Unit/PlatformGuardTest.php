<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Support\Platform\Linux\LinuxPrivateFileGuard;
use TGbotPHP\Support\Platform\MacOS\MacOSPrivateFileGuard;
use TGbotPHP\Support\Platform\PlatformGuard;
use TGbotPHP\Support\Platform\Windows\WindowsPrivateFileGuard;

final class PlatformGuardTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        PlatformGuard::reset();
        putenv('USERDOMAIN');
        putenv('USERNAME');
    }

    public function testForFamilyBuildsEveryPlatformGuard(): void
    {
        self::assertInstanceOf(WindowsPrivateFileGuard::class, PlatformGuard::forFamily('Windows'));
        self::assertInstanceOf(MacOSPrivateFileGuard::class, PlatformGuard::forFamily('Darwin'));
        self::assertInstanceOf(LinuxPrivateFileGuard::class, PlatformGuard::forFamily('Linux'));
        self::assertInstanceOf(LinuxPrivateFileGuard::class, PlatformGuard::forFamily('BSD'));
    }

    public function testUseAndResetControlTheCurrentGuard(): void
    {
        $mac = new MacOSPrivateFileGuard();
        PlatformGuard::use($mac);
        self::assertSame($mac, PlatformGuard::current());

        PlatformGuard::reset();
        self::assertNotSame($mac, PlatformGuard::current());
    }

    public function testPosixGuardsExerciseEveryOperation(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-posix-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $file = $directory . '/secret.txt';
        file_put_contents($file, 'secret');

        try {
            foreach ([new LinuxPrivateFileGuard(), new MacOSPrivateFileGuard()] as $guard) {
                self::assertTrue($guard->restrict($file));
                self::assertSame(PHP_OS_FAMILY !== 'Windows', $guard->isRestricted($file));
                self::assertTrue($guard->makeReadOnly($directory));
                if (PHP_OS_FAMILY !== 'Windows') {
                    self::assertSame(0500, fileperms($directory) & 0777);
                }
                self::assertTrue($guard->restoreWritable($directory));
                self::assertContains($guard->describe(), [
                    'Linux POSIX mode 0600 / 0500',
                    'macOS POSIX mode 0600 / 0500',
                ]);
            }
        } finally {
            @chmod($directory, 0700);
            @unlink($file);
            rmdir($directory);
        }
    }

    public function testPosixGuardsNeverRestrictAFileAsADirectory(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tgbotphp-posix-file-');
        self::assertIsString($file);

        try {
            foreach ([new LinuxPrivateFileGuard(), new MacOSPrivateFileGuard()] as $guard) {
                self::assertFalse($guard->makeReadOnly($file));
            }
        } finally {
            @unlink($file);
        }
    }

    public function testWindowsGuardFailsClosedWithoutAUser(): void
    {
        putenv('USERDOMAIN=');
        putenv('USERNAME=');

        $guard = new WindowsPrivateFileGuard();
        $path = sys_get_temp_dir() . '/tgbotphp-no-user-' . bin2hex(random_bytes(4));

        self::assertFalse($guard->restrict($path));
        self::assertFalse($guard->isRestricted($path));
        self::assertFalse($guard->makeReadOnly($path));
        self::assertFalse($guard->restoreWritable($path));
        self::assertSame('Windows NTFS ACL (icacls owner-only)', $guard->describe());
    }

    public function testWindowsGuardAppliesAclsWhenUserIsAvailable(): void
    {
        $directory = $this->createFakeIcacls(true);
        $path = $directory . '/sample.txt';
        file_put_contents($path, 'sample');

        putenv('USERDOMAIN=TESTDOMAIN');
        putenv('USERNAME=tester');

        try {
            $guard = new WindowsPrivateFileGuard();

            self::assertTrue($guard->restrict($path));
            self::assertTrue($guard->isRestricted($path));
            self::assertTrue($guard->makeReadOnly($directory));
            self::assertTrue($guard->restoreWritable($directory));
            self::assertSame('Windows NTFS ACL (icacls owner-only)', $guard->describe());
        } finally {
            $this->removeFakeIcacls($directory);
            @unlink($path);
            rmdir($directory);
        }
    }

    public function testWindowsGuardReportsFailureWhenIcaclsFails(): void
    {
        $directory = $this->createFakeIcacls(false);

        putenv('USERDOMAIN=TESTDOMAIN');
        putenv('USERNAME=tester');

        try {
            $guard = new WindowsPrivateFileGuard();

            self::assertFalse($guard->isRestricted($directory . '/sample.txt'));
        } finally {
            $this->removeFakeIcacls($directory);
            rmdir($directory);
        }
    }

    /**
     * Put a deterministic icacls shim first in PATH.
     *
     * @return string Temporary directory holding the shim
     */
    private function createFakeIcacls(bool $success): string
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-icacls-' . bin2hex(random_bytes(4));
        mkdir($directory);

        $windows = PHP_OS_FAMILY === 'Windows';
        $name = $windows ? 'icacls.bat' : 'icacls';
        $body = $success
            ? ($windows
                ? "@echo off\r\necho \"C:\\fake\" TESTDOMAIN\\tester:(F)\r\nexit /b 0\r\n"
                : "#!/bin/sh\nprintf '%s\\n' '\"C:\\fake\" TESTDOMAIN\\tester:(F)'\nexit 0\n")
            : ($windows
                ? "@echo off\r\nexit /b 1\r\n"
                : "#!/bin/sh\nexit 1\n");

        file_put_contents($directory . '/' . $name, $body);
        if (!$windows) {
            chmod($directory . '/' . $name, 0755);
        }

        $currentPath = getenv('PATH');
        putenv('PATH=' . $directory . PATH_SEPARATOR . ($currentPath === false ? '' : $currentPath));

        return $directory;
    }

    private function removeFakeIcacls(string $directory): void
    {
        $path = getenv('PATH');
        if (is_string($path)) {
            putenv('PATH=' . implode(PATH_SEPARATOR, array_values(array_filter(
                explode(PATH_SEPARATOR, $path),
                static fn(string $part): bool => $part !== $directory,
            ))));
        }

        @unlink($directory . '/' . (PHP_OS_FAMILY === 'Windows' ? 'icacls.bat' : 'icacls'));
    }
}
