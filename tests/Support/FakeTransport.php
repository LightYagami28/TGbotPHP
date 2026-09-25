<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

use TGbotPHP\Http\HttpResponse;
use TGbotPHP\Http\TransportInterface;

/**
 * In-memory transport recording requests and replaying queued responses
 */
final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{url: string, method: string, fields: array<string, mixed>, multipart: bool, timeout: int}> */
    public array $requests = [];

    /** @var HttpResponse[] */
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

        return array_shift($this->queue)
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

        return array_shift($this->queue) ?? new HttpResponse(404, '');
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
     * @return string[]
     */
    public function methods(): array
    {
        return array_column($this->requests, 'method');
    }
}
