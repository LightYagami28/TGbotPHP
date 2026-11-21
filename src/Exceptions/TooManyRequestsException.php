<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use TGbotPHP\Support\Value;

/**
 * Raised when Telegram answers with HTTP 429 because of flood control.
 *
 * The `retry_after` parameter is exposed so a caller can honour Telegram's
 * backoff instead of guessing a delay.
 */
class TooManyRequestsException extends ApiException
{
    /**
     * Seconds Telegram asked to wait before retrying, never negative.
     */
    public function getRetryAfter(): int
    {
        return max(0, Value::int($this->getParameters()['retry_after'] ?? null));
    }
}
