<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\NetworkException;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Framework\Runner\LongPolling;
use TGbotPHP\Support\Value;
use TGbotPHP\Testing\FakeTransport;
use TGbotPHP\Tests\Support\HandlerFailure;
use TGbotPHP\Tests\Support\Updates;

final class LongPollingTest extends TestCase
{
    private FakeTransport $transport;
    private Bot $bot;
    private LongPolling $polling;

    /** @var list<int> Seconds the runner waited */
    private array $waits = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->bot = new Bot(new Config(Updates::TOKEN, retry: RetryPolicy::none()), $this->transport);
        $this->polling = new LongPolling($this->bot, function (int $seconds): void {
            $this->waits[] = $seconds;
        });
    }

    public function testWaitsForRetryAfterOnTooManyRequests(): void
    {
        $this->transport
            ->queueError(429, 'Too Many Requests: retry after 7', ['retry_after' => 7])
            ->queueResult([]);

        $this->polling->run(timeout: 0, maxIterations: 2);

        self::assertSame([7], $this->waits);
    }

    public function testBacksOffExponentiallyOnNetworkAndServerErrors(): void
    {
        $errors = [];
        $this->bot->onError(function (\Throwable $e) use (&$errors): void {
            $errors[] = $e::class;
        });

        $this->transport
            ->queueException(new NetworkException('cURL error: timeout'))
            ->queueError(502, 'Bad Gateway')
            ->queueException(new NetworkException('cURL error: reset'))
            ->queueResult([])
            ->queueException(new NetworkException('cURL error: reset'));

        $this->polling->run(timeout: 0, maxIterations: 5);

        // The successful call resets the backoff
        self::assertSame([1, 2, 4, 1], $this->waits);
        self::assertSame([NetworkException::class, ApiException::class, NetworkException::class, NetworkException::class], $errors);
    }

    public function testBackoffIsCapped(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->transport->queueError(500, 'Internal Server Error');
        }

        $this->polling->run(timeout: 0, maxIterations: 8);

        self::assertSame([1, 2, 4, 8, 16, 30, 30, 30], $this->waits);
    }

    public function testInvalidTokenStopsPolling(): void
    {
        $this->transport->queueError(401, 'Unauthorized');

        try {
            $this->polling->run(timeout: 0);
            self::fail('Expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->getCode());
        }

        self::assertFalse($this->polling->isRunning());
        self::assertSame([], $this->waits);
    }

    public function testHandlerErrorsDoNotStopPolling(): void
    {
        $processed = [];
        $this->bot->fallback(function (stdClass $message) use (&$processed): void {
            $processed[] = Value::path($message, 'text');

            if (Value::path($message, 'text') === 'boom') {
                throw new HandlerFailure('handler failed');
            }
        });

        $this->transport->queueResult([Updates::message('boom'), Updates::message('after')]);

        $log = ini_set('error_log', '/dev/null');
        try {
            $this->polling->run(timeout: 0, maxIterations: 1);
        } finally {
            ini_set('error_log', $log === false ? '' : $log);
        }

        self::assertSame(['boom', 'after'], $processed);
    }

    public function testDispatchesStartAndStopEvents(): void
    {
        $events = [];
        $this->bot->on('polling.started', function () use (&$events): void {
            $events[] = 'started';
        });
        $this->bot->on('polling.stopped', function () use (&$events): void {
            $events[] = 'stopped';
        });

        $this->polling->run(timeout: 0, maxIterations: 1);

        self::assertSame(['started', 'stopped'], $events);
    }

    public function testSendsTimeoutLimitAndAllowedUpdates(): void
    {
        $this->transport->queueResult([]);

        $this->polling->run(timeout: 25, allowedUpdates: ['message'], limit: 10, maxIterations: 1);

        $request = $this->transport->requests()[0];
        self::assertSame('getUpdates', $request['method']);
        self::assertSame(['limit' => '10', 'timeout' => '25', 'allowed_updates' => '["message"]'], $request['fields']);
        self::assertSame(35, $request['timeout']);
    }

    public function testConfirmsProcessedUpdatesWithoutWaiting(): void
    {
        $this->transport->queueResult([Updates::message('a'), ['update_id' => 900] + Updates::message('b')]);

        $this->polling->run(timeout: 25, maxIterations: 1);

        self::assertSame(['getUpdates', 'getUpdates'], $this->transport->methods());
        // Stopping must not hold the process for another long poll
        self::assertSame(['offset' => '901', 'limit' => '1', 'timeout' => '0'], $this->transport->lastRequest()['fields']);
    }

    public function testNothingToConfirmWithoutUpdates(): void
    {
        $this->transport->queueResult([]);

        $this->polling->run(timeout: 0, maxIterations: 1);

        self::assertSame(['getUpdates'], $this->transport->methods());
    }

    public function testWaitsAtLeastOneSecondOnTooManyRequests(): void
    {
        $this->transport->queueError(429, 'Too Many Requests', ['retry_after' => 0])->queueResult([]);

        $this->polling->run(timeout: 0, maxIterations: 2);

        self::assertSame([1], $this->waits);
    }
}
