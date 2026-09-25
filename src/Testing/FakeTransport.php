<?php

declare(strict_types=1);

namespace TGbotPHP\Testing;

use TGbotPHP\Http\HttpResponse;
use TGbotPHP\Http\TransportInterface;

/**
 * In-memory transport: records requests and answers with queued responses
 *
 * Lets you test a bot without network access:
 *
 *     $transport = new FakeTransport();
 *     $bot = new Bot($token, $transport);
 *
 *     $transport->queueResult(['message_id' => 1, 'date' => 0, 'chat' => ['id' => 1, 'type' => 'private']]);
 *     $transport->queueError(403, 'Forbidden: bot was blocked by the user');
 *
 * Without queued responses, methods starting with send, get, edit... receive
 * a message-like object and the others receive true. See BotTester for a
 * ready-made bot.
 */
final class FakeTransport implements TransportInterface
{
    /** @var list<array{url: string, method: string, fields: array<string, mixed>, multipart: bool, timeout: int}> Every request, oldest first */
    public private(set) array $requests = [];

    /** @var list<HttpResponse|\Throwable> */
    private array $queue = [];

    /** Result returned when the queue is empty (null: guessed from the method name) */
    public mixed $defaultResult = null;

    public function queueResult(mixed $result): self
    {
        return $this->queueJson(['ok' => true, 'result' => $result]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function queueError(int $code, string $description, array $parameters = []): self
    {
        $body = ['ok' => false, 'error_code' => $code, 'description' => $description];
        if ($parameters !== []) {
            $body['parameters'] = $parameters;
        }

        return $this->queueJson($body, $code);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function queueJson(array $body, int $status = 200): self
    {
        return $this->queueRaw(json_encode($body, JSON_THROW_ON_ERROR), $status);
    }

    public function queueRaw(string $body, int $status = 200): self
    {
        $this->queue[] = new HttpResponse($status, $body);
        return $this;
    }

    /**
     * Throw instead of answering, like a transport that cannot reach Telegram
     */
    public function queueException(\Throwable $exception): self
    {
        $this->queue[] = $exception;
        return $this;
    }

    private function next(): ?HttpResponse
    {
        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    #[\Override]
    public function post(string $url, array $fields, bool $multipart, int $timeout): HttpResponse
    {
        $this->requests[] = [
            'url' => $url,
            'method' => basename($url),
            'fields' => $fields,
            'multipart' => $multipart,
            'timeout' => $timeout,
        ];

        return $this->next()
            ?? new HttpResponse(200, json_encode(['ok' => true, 'result' => $this->defaultResultFor($url)], JSON_THROW_ON_ERROR));
    }

    /**
     * Methods that send, edit or read something return an object, the others return true
     */
    private function defaultResultFor(string $url): mixed
    {
        if ($this->defaultResult !== null) {
            return $this->defaultResult;
        }

        $method = basename($url);

        return preg_match('/^(send|forward|copy|edit|stop|get|create|upload)/', $method) === 1
            ? ['message_id' => 1, 'date' => 1700000000, 'chat' => ['id' => 1, 'type' => 'private']]
            : true;
    }

    #[\Override]
    public function get(string $url, int $timeout): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'method' => 'GET', 'fields' => [], 'multipart' => false, 'timeout' => $timeout];

        return $this->next() ?? new HttpResponse(404, '');
    }

    #[\Override]
    public function download(string $url, string $destination, int $timeout): int
    {
        $response = $this->get($url, $timeout);
        file_put_contents($destination, $response->body);

        return $response->statusCode;
    }

    /**
     * @return array{url: string, method: string, fields: array<string, mixed>, multipart: bool, timeout: int}
     */
    public function lastRequest(): array
    {
        if ($this->requests === []) {
            throw new \LogicException('No request was sent');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * Forget the recorded requests and the queued responses
     */
    public function reset(): void
    {
        $this->requests = [];
        $this->queue = [];
    }

    /**
     * @return string[]
     */
    public function methods(): array
    {
        return array_column($this->requests, 'method');
    }
}
