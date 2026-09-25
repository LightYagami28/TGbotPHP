<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Runner;

use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\NetworkException;
use TGbotPHP\Exceptions\TooManyRequestsException;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;
use Throwable;

/**
 * Receives updates with getUpdates until stopped
 *
 * Network and server errors are retried with exponential backoff; 429 errors
 * wait for retry_after. 401, 404 and 409 (webhook still set) stop the loop.
 */
final class LongPolling
{
    /** Errors that retrying cannot fix */
    private const array FATAL_ERRORS = [401, 404, 409];

    private const int MAX_BACKOFF = 30;

    public private(set) bool $running = false;

    /** Next update_id to request */
    private ?int $offset = null;

    private int $failures = 0;

    public function __construct(private readonly Bot $bot) {}

    /**
     * @param string[]|null $allowedUpdates
     * @param int $maxIterations Stop after this many getUpdates calls (0: run until stop())
     */
    public function run(int $timeout = 30, ?array $allowedUpdates = null, ?int $limit = null, int $maxIterations = 0): void
    {
        $this->running = true;
        $this->bot->getEvents()->dispatch('polling.started', $this->bot);

        try {
            for ($i = 0; $this->running && ($maxIterations === 0 || $i < $maxIterations); $i++) {
                $this->processBatch($this->fetch($timeout, $allowedUpdates, $limit));
            }
        } finally {
            $this->running = false;
            $this->acknowledge($allowedUpdates);
            $this->bot->getEvents()->dispatch('polling.stopped', $this->bot);
        }
    }

    /**
     * Stop after the update being processed
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * @param string[]|null $allowedUpdates
     * @return list<array<string, mixed>>
     */
    private function fetch(int $timeout, ?array $allowedUpdates, ?int $limit): array
    {
        try {
            $updates = $this->bot->getUpdates($this->offset, $limit, $timeout, $allowedUpdates);
            $this->failures = 0;

            return $updates;
        } catch (TooManyRequestsException $e) {
            sleep(max(1, $e->getRetryAfter()));
        } catch (ApiException $e) {
            $this->recover($e, fatal: in_array($e->getCode(), self::FATAL_ERRORS, true));
        } catch (NetworkException $e) {
            $this->recover($e, fatal: false);
        }

        return [];
    }

    private function recover(ApiException|NetworkException $e, bool $fatal): void
    {
        if ($fatal) {
            throw $e;
        }

        $this->bot->getEvents()->dispatch('error', $e, null, $this->bot);
        sleep(min(self::MAX_BACKOFF, 2 ** min($this->failures++, 5)));
    }

    /**
     * @param list<array<string, mixed>> $updates
     */
    private function processBatch(array $updates): void
    {
        foreach ($updates as $data) {
            if (!$this->running) {
                return;
            }

            $updateId = Value::nullableInt($data['update_id'] ?? null);

            if ($updateId !== null) {
                $this->offset = $updateId + 1;
                $this->process($data);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function process(array $data): void
    {
        try {
            $this->bot->processUpdate(UpdateParser::fromArray($data));
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());
        }
    }

    /**
     * Confirm the processed updates, so they are not delivered again
     *
     * @param string[]|null $allowedUpdates
     */
    private function acknowledge(?array $allowedUpdates): void
    {
        if ($this->offset === null) {
            return;
        }

        try {
            $this->bot->getUpdates($this->offset, 1, 0, $allowedUpdates);
        } catch (Throwable) {
            // Best effort: unconfirmed updates are simply delivered again
        }
    }
}
