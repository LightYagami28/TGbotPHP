<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Exception thrown when Telegram answers with HTTP 429 (flood control)
 */
class TooManyRequestsException extends ApiException
{
    public function getRetryAfter(): int
    {
        return (int) ($this->getParameters()['retry_after'] ?? 0);
    }
}
