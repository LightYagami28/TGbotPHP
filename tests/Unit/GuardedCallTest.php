<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
}
