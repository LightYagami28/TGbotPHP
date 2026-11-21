<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TGbotPHP\Exceptions\GuardedCallException;
use TGbotPHP\Support\GuardedCall;

final class GuardedCallTest extends TestCase
{
    public function testTheStaticHelperHasNoPublicConstructor(): void
    {
        $class = new ReflectionClass(GuardedCall::class);
        $constructor = $class->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        $constructor->invoke($class->newInstanceWithoutConstructor());
    }

    public function testReturnsTheOperationResultWhenNoWarningIsRaised(): void
    {
        self::assertSame('ok', GuardedCall::run(static fn(): string => 'ok'));
    }

    public function testLogsAndReturnsFalseWhenTheOperationRaisesAWarning(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tgbotphp-err');
        self::assertIsString($log);
        $previous = ini_set('error_log', $log);

        try {
            $result = GuardedCall::run(static function (): bool {
                trigger_error('guarded warning', E_USER_WARNING);

                return true;
            });

            self::assertFalse($result);
            self::assertStringContainsString('[TGbotPHP] guarded warning', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            unlink($log);
        }
    }

    public function testThePromotedWarningUsesADedicatedExceptionKeepingItsMetadata(): void
    {
        $exception = new GuardedCallException('guarded warning', 0, E_USER_WARNING, __FILE__, 123);

        self::assertSame('guarded warning', $exception->getMessage());
        self::assertSame(E_USER_WARNING, $exception->getSeverity());
        self::assertSame(__FILE__, $exception->getFile());
        self::assertSame(123, $exception->getLine());
    }

    public function testRestoresThePreviousErrorHandlerWhenTheOperationThrows(): void
    {
        $sentinel = static fn(): bool => true;
        set_error_handler($sentinel);

        try {
            try {
                GuardedCall::run(static function (): never {
                    throw new LogicException('boom');
                });
                self::fail('The operation exception was swallowed');
            } catch (LogicException $exception) {
                self::assertSame('boom', $exception->getMessage());
            }

            $installed = set_error_handler(static fn(): bool => true);
            self::assertSame($sentinel, $installed);
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }
}
