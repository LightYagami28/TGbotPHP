<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

/**
 * How requests rejected by flood control (HTTP 429) are retried
 */
final readonly class RetryPolicy
{
    /**
     * @param int $maxRetries Retries after a 429 error (0 disables retrying)
     * @param int $maxDelay Never wait longer than this many seconds; longer waits throw instead
     */
    public function __construct(
        public int $maxRetries = 1,
        public int $maxDelay = 30,
    ) {
        if ($maxRetries < 0 || $maxDelay < 0) {
            throw new \InvalidArgumentException('Retry settings cannot be negative');
        }
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public function allows(int $attempt, int $retryAfter): bool
    {
        return $attempt < $this->maxRetries && $retryAfter <= $this->maxDelay;
    }
}
