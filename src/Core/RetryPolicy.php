<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

/**
 * How failed requests are retried
 *
 * Flood control (HTTP 429) waits for retry_after, up to maxDelay.
 * Network failures and HTTP 5xx responses are retried with exponential
 * backoff, up to transientRetries. Webhook handling does not wait: a
 * blocked PHP-FPM worker cannot answer Telegram.
 */
final readonly class RetryPolicy
{
    /**
     * @param int $maxRetries Retries after a 429 error (0 disables them)
     * @param int $maxDelay Never wait longer than this many seconds; longer waits throw instead. 0 retries immediately
     * @param int $transientRetries Retries after a network error or an HTTP 5xx response (0 disables them)
     */
    public function __construct(
        public int $maxRetries = 1,
        public int $maxDelay = 30,
        public int $transientRetries = 1,
    ) {
        if ($maxRetries < 0 || $maxDelay < 0 || $transientRetries < 0) {
            throw new \InvalidArgumentException('Retry settings cannot be negative');
        }
    }

    public static function none(): self
    {
        return new self(0, 0, 0);
    }

    public function allows(int $attempt, int $retryAfter): bool
    {
        return $attempt < $this->maxRetries && $retryAfter <= $this->maxDelay;
    }

    public function allowsTransient(int $attempt): bool
    {
        return $attempt < $this->transientRetries;
    }

    /**
     * Seconds to wait before a transient retry. $attempt is 0 for the first retry.
     * A maxDelay of 0 retries immediately, which keeps tests from sleeping.
     */
    public function transientDelay(int $attempt): int
    {
        if ($this->maxDelay === 0 || $attempt < 0) {
            return 0;
        }

        return min($this->maxDelay, 2 ** min($attempt, 5));
    }
}
