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
        $inside = $inside === false ? [] : $inside;
        $outside = $outside === false ? [] : $outside;

        // La pulizia passa dallo stesso helper canonicalizzato usato in produzione:
        // nessun percorso derivato dall'ambiente raggiunge direttamente unlink().
        SafeFileRemover::remove($inside, $this->root);
        SafeFileRemover::remove($outside, $this->base);

        foreach ([...$inside, ...$outside] as $entry) {
            if (is_dir($entry)) {
                @rmdir($entry);
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

    public function testIgnoresDotEntries(): void
    {
        $inside = $this->root . '/entry.cache';
        file_put_contents($inside, 'inside');

        SafeFileRemover::remove([$this->root . '/.', $this->root . '/..'], $this->root);

        self::assertDirectoryExists($this->root);
        self::assertFileExists($inside);
    }
}
