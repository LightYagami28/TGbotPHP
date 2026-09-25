<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Utilities\Logger;

final class LoggerTest extends TestCase
{
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tgbotphp-log');
        self::assertIsString($file);
        $this->file = $file;
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testWritesLevelMessageAndContext(): void
    {
        new Logger($this->file)->info('User {id} joined', ['id' => 42]);

        self::assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[INFO\] User 42 joined \{"id":42\}\n$/',
            $this->contents(),
        );
    }

    public function testSkipsLevelsBelowTheMinimum(): void
    {
        $logger = new Logger($this->file, minLevel: 'warning');

        $logger->debug('debug');
        $logger->info('info');
        $logger->warning('warning');
        $logger->error('error');

        self::assertSame(['[WARNING] warning', '[ERROR] error'], $this->entries());
    }

    public function testCanBeDisabled(): void
    {
        $logger = new Logger($this->file);

        $logger->disable();
        $logger->error('hidden');
        $logger->enable();
        $logger->error('shown');

        self::assertSame(['[ERROR] shown'], $this->entries());
    }

    public function testUserInputCannotForgeEntries(): void
    {
        new Logger($this->file)->info('Message: {text}', ['text' => "hi\n[2030-01-01 00:00:00] [ERROR] forged"]);

        self::assertCount(1, explode("\n", trim($this->contents())));
    }

    private function contents(): string
    {
        return (string) file_get_contents($this->file);
    }

    /**
     * @return list<string> Entries without timestamp and context
     */
    private function entries(): array
    {
        $lines = array_filter(explode("\n", $this->contents()), static fn(string $line): bool => $line !== '');

        return array_values(array_map(static fn(string $line): string => substr($line, 22), $lines));
    }

    public function testLevelsAreCaseInsensitive(): void
    {
        $logger = new Logger($this->file, minLevel: 'unknown');

        $logger->log('warning', 'lowercase level');
        $logger->debug('debug is the minimum for unknown levels');

        self::assertSame(['[WARNING] lowercase level', '[DEBUG] debug is the minimum for unknown levels'], $this->entries());
    }

    public function testCarriageReturnsAndStringableContext(): void
    {
        $name = new class implements \Stringable {
            #[\Override]
            public function __toString(): string
            {
                return 'Ada';
            }
        };

        new Logger($this->file)->info("Hello {name}\r[ERROR] forged", ['name' => $name]);

        self::assertSame(['[INFO] Hello Ada\\r[ERROR] forged {"name":{}}'], $this->entries());
    }
}
