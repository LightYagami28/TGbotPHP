<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

/**
 * Thrown by test handlers to simulate a bug in user code
 */
final class HandlerFailure extends \RuntimeException
{
}
