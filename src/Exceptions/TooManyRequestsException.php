<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use TGbotPHP\Support\Value;

/**
 * Exception thrown when Telegram answers with HTTP 429 (flood control)
 */
class TooManyRequestsException extends ApiException
{
    public function getRetryAfter(): int
    {
        return max(0, Value::int($this->getParameters()['retry_after'] ?? null));
    }
}
