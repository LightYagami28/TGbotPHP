<?php

declare(strict_types=1);

namespace TGbotPHP\Traits;

use CURLFile;
use CURLStringFile;
use JsonSerializable;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\TooManyRequestsException;
use TGbotPHP\Http\CurlTransport;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Support\Value;
use TGbotPHP\Types\InputFile;

/**
 * HTTP client functionality for API calls
 *
 * Expects the using class to expose a Config through `$this->config`.
 */
trait HttpClientTrait
{
    /** Parameters never written to the debug log */
    private const array SECRET_FIELDS = ['secret_token', 'provider_token'];

    private ?TransportInterface $transport = null;

    /**
     * Replace the HTTP transport (useful for tests or custom HTTP clients)
     */
    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport ??= new CurlTransport();
    }

    /**
     * Make HTTP request to Telegram API
     *
     * @param array<string, mixed> $data
     * @return mixed The "result" field of the API response, or null when $returnResponse is false
     *
     * @throws ApiException
     */
    protected function httpRequest(
        string $method,
        array $data = [],
        bool $returnResponse = false
    ): mixed {
        [$fields, $multipart] = self::prepareFields($data);

        $url = $this->config->apiBaseUrl . '/bot' . $this->config->token . '/' . rawurlencode($method);
        $timeout = $this->config->timeout + max(0, Value::int($data['timeout'] ?? null));

        $attempt = 0;

        while (true) {
            $this->debugLog("→ $method " . self::describeFields($fields));

            $response = $this->getTransport()->post($url, $fields, $multipart, $timeout);

            $this->debugLog("← $method [{$response->statusCode}] " . substr($response->body, 0, 1000));

            try {
                return $this->parseResponse($method, $response->statusCode, $response->body, $returnResponse);
            } catch (TooManyRequestsException $e) {
                $retryAfter = $e->getRetryAfter();

                if (!$this->config->retry->allows($attempt, $retryAfter)) {
                    throw $e;
                }

                $attempt++;
                if ($retryAfter > 0) {
                    sleep($retryAfter);
                }
            }
        }
    }

    /**
     * Call an API method and return its result
     *
     * Values in $options are merged over $params, so any optional or newer
     * API parameter can be passed without a dedicated argument.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @throws ApiException
     */
    protected function apiCall(string $method, array $params = [], array $options = []): mixed
    {
        return $this->httpRequest($method, array_merge($params, $options), returnResponse: true);
    }

    /**
     * Call a method returning an object (User, Message, Chat, ...)
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    protected function apiCallObject(string $method, array $params = [], array $options = []): array
    {
        $result = $this->apiCall($method, $params, $options);

        if (!Value::isMap($result)) {
            throw self::unexpectedResult($method, 'an object', $result);
        }

        return $result;
    }

    /**
     * Call a method returning an array of objects
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     *
     * @throws ApiException
     */
    protected function apiCallList(string $method, array $params = [], array $options = []): array
    {
        $result = $this->apiCall($method, $params, $options);

        if (!Value::isListOfMaps($result)) {
            throw self::unexpectedResult($method, 'an array of objects', $result);
        }

        return $result;
    }

    /**
     * Call an edit method: returns the edited Message, or true for inline messages
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     *
     * @throws ApiException
     */
    protected function apiCallObjectOrTrue(string $method, array $params = [], array $options = []): array|bool
    {
        $result = $this->apiCall($method, $params, $options);

        if ($result !== true && !Value::isMap($result)) {
            throw self::unexpectedResult($method, 'an object or true', $result);
        }

        return $result;
    }

    /**
     * Call a method returning True on success
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @throws ApiException
     */
    protected function apiCallBool(string $method, array $params = [], array $options = []): bool
    {
        $result = $this->apiCall($method, $params, $options);

        if (!is_bool($result)) {
            throw self::unexpectedResult($method, 'a boolean', $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @throws ApiException
     */
    protected function apiCallInt(string $method, array $params = [], array $options = []): int
    {
        $result = $this->apiCall($method, $params, $options);

        if (!is_int($result)) {
            throw self::unexpectedResult($method, 'an integer', $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     *
     * @throws ApiException
     */
    protected function apiCallString(string $method, array $params = [], array $options = []): string
    {
        $result = $this->apiCall($method, $params, $options);

        if (!is_string($result)) {
            throw self::unexpectedResult($method, 'a string', $result);
        }

        return $result;
    }

    private static function unexpectedResult(string $method, string $expected, mixed $result): ApiException
    {
        return new ApiException(
            "Unexpected result from $method: expected $expected, got " . get_debug_type($result),
            0,
            ['ok' => true, 'result' => $result],
            $method
        );
    }

    /**
     * Decode an API response and throw on errors
     *
     * @throws ApiException
     */
    private function parseResponse(string $method, int $statusCode, string $body, bool $returnResponse): mixed
    {
        $decoded = $body !== '' ? json_decode($body, true) : null;

        if (!is_array($decoded)) {
            $message = $body === ''
                ? "Empty response from Telegram API (HTTP $statusCode)"
                : "Invalid JSON response from Telegram API (HTTP $statusCode)";

            throw new ApiException($message, $statusCode, [], $method);
        }

        $decoded = Value::map($decoded);

        if (($decoded['ok'] ?? false) !== true) {
            $code = Value::int($decoded['error_code'] ?? null, $statusCode);
            $description = Value::string($decoded['description'] ?? null, "HTTP $statusCode from Telegram API");

            if ($code === 429) {
                throw new TooManyRequestsException($description, $code, $decoded, $method);
            }

            throw new ApiException($description, $code, $decoded, $method);
        }

        return $returnResponse ? ($decoded['result'] ?? null) : null;
    }

    /**
     * Convert method parameters to HTTP fields
     *
     * - null values are dropped
     * - booleans become "true"/"false"
     * - arrays and JsonSerializable objects are JSON encoded
     * - InputFile instances are uploaded; nested ones (media groups, stickers)
     *   are replaced with "attach://" references
     *
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public static function prepareFields(array $data): array
    {
        $fields = [];
        $files = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof InputFile) {
                $files[$key] = $value->toCurlFile();
                continue;
            }

            if (is_bool($value)) {
                $fields[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value) || $value instanceof JsonSerializable) {
                $fields[$key] = json_encode(
                    self::extractAttachments($value, $files),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } elseif (is_scalar($value) || $value instanceof \Stringable) {
                $fields[$key] = (string) $value;
            } else {
                throw new \InvalidArgumentException("Unsupported value for parameter '$key'");
            }
        }

        return [$fields + $files, $files !== []];
    }

    /**
     * Replace nested InputFile objects with attach:// references
     *
     * @param array<string, CURLFile|CURLStringFile> $files
     */
    private static function extractAttachments(mixed $value, array &$files): mixed
    {
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if ($value instanceof InputFile) {
            $name = 'file' . count($files);
            $files[$name] = $value->toCurlFile();

            return 'attach://' . $name;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::extractAttachments($item, $files);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function describeFields(array $fields): string
    {
        $described = [];
        foreach ($fields as $key => $value) {
            $described[$key] = match (true) {
                in_array($key, self::SECRET_FIELDS, true) => '<redacted>',
                $value instanceof CURLFile => '<file ' . $value->getPostFilename() . '>',
                $value instanceof CURLStringFile => '<file ' . $value->postname . '>',
                default => $value,
            };
        }

        return (string) json_encode($described, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function debugLog(string $line): void
    {
        if (!$this->config->debug) {
            return;
        }

        // One entry per line, whatever the response contains
        $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ['\\r', '\\n'], $line) . PHP_EOL;

        if ($this->config->debugFile !== false) {
            error_log($line, 3, $this->config->debugFile);
        } else {
            error_log(rtrim($line));
        }
    }
}
