<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

use InvalidArgumentException;

/**
 * Immutable description of how failed requests may be retried.
 *
 * Flood control (HTTP 429) honours Telegram's `retry_after` parameter, but
 * never waits longer than {@see self::$maxDelay}. Network errors and HTTP 5xx
 * responses use exponential backoff bounded by {@see self::$transientRetries}.
 *
 * Webhook handlers do not sleep: a blocked PHP-FPM worker cannot answer
 * Telegram in time, so operators should keep the delays short for webhooks.
 */
final readonly class RetryPolicy
{
    /**
     * @param int $maxRetries Number of retries after a 429 response; 0 disables them
     * @param int $maxDelay Maximum wait in seconds; longer waits throw instead, and 0 retries immediately
     * @param int $transientRetries Number of retries after a network error or an HTTP 5xx response; 0 disables them
     *
     * @throws InvalidArgumentException When any setting is negative
     */
    public function __construct(
        public int $maxRetries = 1,
        public int $maxDelay = 30,
        public int $transientRetries = 1,
    ) {
        if ($maxRetries < 0 || $maxDelay < 0 || $transientRetries < 0) {
            throw new InvalidArgumentException('Retry settings cannot be negative');
        }
    }

    /**
     * A policy that never retries and never waits.
     *
     * Useful in tests and in webhook handlers that must answer immediately.
     */
    public static function none(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * Whether another attempt is allowed after a flood control response.
     *
     * @param int $attempt Zero for the first retry, then increasing
     * @param int $retryAfter Seconds Telegram asked to wait
     */
    public function allows(int $attempt, int $retryAfter): bool
    {
        return $attempt < $this->maxRetries && $retryAfter <= $this->maxDelay;
    }

    /**
     * Whether another attempt is allowed after a transient failure.
     *
     * @param int $attempt Zero for the first retry, then increasing
     */
    public function allowsTransient(int $attempt): bool
    {
        return $attempt < $this->transientRetries;
    }

    /**
     * Seconds to wait before a transient retry, capped at {@see self::$maxDelay}.
     *
     * A zero delay retries immediately and keeps tests from sleeping. The
     * exponent is capped at 5 to avoid an overflow on very high attempt
     * numbers; negative attempts are treated as an immediate retry.
     *
     * @param int $attempt Zero for the first retry, then increasing
     */
    public function transientDelay(int $attempt): int
    {
        if ($this->maxDelay === 0 || $attempt < 0) {
            return 0;
        }

        return min($this->maxDelay, 2 ** min($attempt, 5));
    }
}
