<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Support\SafeFileRemover;

final class SafeFileRemoverTest extends TestCase
{
    private string $base;
    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/tgbotphp-remover-' . bin2hex(random_bytes(4));
        $this->root = $this->base . '/inside';
        mkdir($this->base);
        mkdir($this->root);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $inside = glob($this->root . '/*');
        $outside = glob($this->base . '/*');

        foreach ([...($inside === false ? [] : $inside), ...($outside === false ? [] : $outside)] as $file) {
            if (is_dir($file)) {
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($this->root);
        @rmdir($this->base);
    }

    public function testDeletesOnlyFilesInsideTheAuthorizedRoot(): void
    {
        $inside = $this->root . '/entry.cache';
        $outside = $this->base . '/outside.cache';
        file_put_contents($inside, 'inside');
        file_put_contents($outside, 'outside');

        SafeFileRemover::remove([$inside, $outside], $this->root);

        self::assertFileDoesNotExist($inside);
        self::assertFileExists($outside);
    }

    public function testKeepsDirectoriesAndMissingPaths(): void
    {
        $nested = $this->root . '/nested';
        mkdir($nested);

        SafeFileRemover::remove([$nested, $this->root . '/missing.cache'], $this->root);

        self::assertDirectoryExists($nested);
    }

    public function testMissingRootRemovesNothing(): void
    {
        $inside = $this->root . '/entry.cache';
        file_put_contents($inside, 'inside');

        SafeFileRemover::remove([$inside], $this->base . '/missing-root');

        self::assertFileExists($inside);
    }
}
