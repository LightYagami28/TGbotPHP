<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

use CurlHandle;
use InvalidArgumentException;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Exceptions\NetworkException;
use TGbotPHP\Security\LogRedactor;
use TGbotPHP\Support\GuardedCall;

/**
 * cURL based HTTP transport.
 *
 * Responses read into memory are capped so a download without a `file_size`
 * cannot grow without a limit. The download() method streams directly to disk
 * and is not subject to that cap.
 */
final class CurlTransport implements TransportInterface
{
    /** Same ceiling as the in-memory download limit of the API client. */
    public const int MAX_RESPONSE_BYTES = 20 * 1024 * 1024;

    /** Reused handle: consecutive requests keep the connection and TLS session open. */
    private ?CurlHandle $handle = null;

    /**
     * @param int $connectTimeout Seconds allowed for the TCP connection
     * @param int $maxResponseBytes Maximum size of a response read into memory
     *
     * @throws InvalidArgumentException When a limit is not positive
     */
    public function __construct(
        private readonly int $connectTimeout = 5,
        private readonly int $maxResponseBytes = self::MAX_RESPONSE_BYTES,
    ) {
        if ($connectTimeout < 1 || $maxResponseBytes < 1) {
            throw new InvalidArgumentException('Transport timeout and response limit must be positive');
        }
    }

    /**
     * Send a POST request as a form or as multipart/form-data.
     *
     * @param array<string, mixed> $fields Already prepared request fields
     * @param bool $multipart Whether files require a multipart body
     * @param int $timeout Request timeout in seconds
     *
     * @throws NetworkException When the URL is invalid or the request fails
     */
    #[\Override]
    public function post(string $url, array $fields, bool $multipart, int $timeout): HttpResponse
    {
        $options = [
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => $timeout,
        ];

        if ($multipart) {
            // Passing an array makes cURL build the boundary and each file part
            $options[CURLOPT_POSTFIELDS] = $fields;

            return $this->execute($url, $options);
        }

        // The separator must not depend on arg_separator.output in php.ini
        $options[CURLOPT_POSTFIELDS] = http_build_query($fields, '', '&');
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];

        return $this->execute($url, $options);
    }

    /**
     * Send a GET request and return the raw response.
     *
     * @throws NetworkException When the URL is invalid or the request fails
     */
    #[\Override]
    public function get(string $url, int $timeout): HttpResponse
    {
        return $this->execute($url, [
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
    }

    /**
     * Stream a GET response to a file, leaving partial files behind on failure.
     *
     * @throws NetworkException When the destination cannot be opened or the request fails
     */
    #[\Override]
    public function download(string $url, string $destination, int $timeout): int
    {
        $file = GuardedCall::run(static fn() => fopen($destination, 'wb'));

        if (!is_resource($file)) {
            throw new NetworkException("Unable to open $destination for writing");
        }

        try {
            $curl = $this->prepare($url, [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FILE => $file,
            ]);

            if (curl_exec($curl) !== true) {
                throw new NetworkException(self::curlFailure($curl), curl_errno($curl));
            }

            return curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } finally {
            fclose($file);
        }
    }

    /**
     * Execute a request and collect its body in memory up to the configured cap.
     *
     * @param array<int, mixed> $options cURL options for this request
     *
     * @throws NetworkException When the response is too large or the request fails
     */
    private function execute(string $url, array $options): HttpResponse
    {
        $body = '';
        $limited = false;
        $curl = $this->prepare($url, $options + [
            CURLOPT_WRITEFUNCTION => function (CurlHandle $curl, string $chunk) use (&$body, &$limited): int {
                if (strlen($body) + strlen($chunk) > $this->maxResponseBytes) {
                    $limited = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        if (curl_exec($curl) !== true) {
            if ($limited) {
                throw new NetworkException('Response exceeded the transport byte limit', curl_errno($curl));
            }

            throw new NetworkException(self::curlFailure($curl), curl_errno($curl));
        }

        return new HttpResponse(curl_getinfo($curl, CURLINFO_HTTP_CODE), $body);
    }

    /**
     * Reset and configure the shared handle with safe defaults.
     *
     * Redirects are disabled and the protocol restricted to HTTPS (plain HTTP
     * only for the documented loopback test server) so a server response cannot
     * make the transport read a local file.
     *
     * @param array<int, mixed> $options cURL options for this request
     *
     * @throws NetworkException When the URL is invalid or cURL cannot be initialised
     */
    private function prepare(string $url, array $options): CurlHandle
    {
        if (!self::isValidUrl($url)) {
            throw new NetworkException('Invalid HTTP transport URL');
        }

        if ($this->handle === null) {
            $handle = curl_init();

            if ($handle === false) {
                throw new NetworkException('Failed to initialize cURL');
            }

            $this->handle = $handle;
        } else {
            curl_reset($this->handle);
        }

        curl_setopt_array($this->handle, [CURLOPT_URL => $url] + $options + [
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            // HTTPS is mandatory in production; plain HTTP is accepted only for
            // the loopback test server documented in CurlTransportTest.
            CURLOPT_PROTOCOLS => self::isLoopbackTestUrl($url) ? CURLPROTO_HTTPS | CURLPROTO_HTTP : CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'TGbotPHP/' . ApiClient::VERSION,
        ]);

        return $this->handle;
    }

    /**
     * HTTPS URLs, plus plain HTTP only for a loopback test server.
     *
     * Credentials and control characters are always rejected: they could forge
     * a header or hide the real destination from the logs.
     */
    private static function isValidUrl(string $url): bool
    {
        $parts = parse_url($url);

        return $parts !== false && self::isAllowedHost($parts) && preg_match('/[\x00-\x20\x7f]/', $url) !== 1;
    }

    /**
     * @param array<string, mixed> $parts Components returned by parse_url()
     */
    private static function isAllowedHost(array $parts): bool
    {
        if (!self::hasUsableHost($parts)) {
            return false;
        }

        return self::isAllowedScheme($parts);
    }

    /**
     * A string host is required, with no embedded credentials.
     *
     * @param array<string, mixed> $parts Components returned by parse_url()
     */
    private static function hasUsableHost(array $parts): bool
    {
        $host = $parts['host'] ?? '';

        return is_string($host) && $host !== '' && !isset($parts['user']) && !isset($parts['pass']);
    }

    /**
     * HTTPS anywhere; plain HTTP only for a loopback test server.
     *
     * @param array<string, mixed> $parts Components returned by parse_url()
     */
    private static function isAllowedScheme(array $parts): bool
    {
        $scheme = $parts['scheme'] ?? '';

        if ($scheme === 'https') {
            return true;
        }

        $host = $parts['host'] ?? '';

        return $scheme === 'http' && is_string($host) && self::isLoopbackHost($host);
    }

    /** Whether the URL is the plain-HTTP loopback server used by the tests. */
    private static function isLoopbackTestUrl(string $url): bool
    {
        $parts = parse_url($url);

        return $parts !== false
            && ($parts['scheme'] ?? '') === 'http'
            && isset($parts['host'])
            && self::isLoopbackHost($parts['host']);
    }

    /** Only localhost, 127.0.0.0/8 and ::1 count as loopback. */
    private static function isLoopbackHost(string $host): bool
    {
        if (strcasecmp($host, 'localhost') === 0) {
            return true;
        }

        // parse_url() keeps IPv6 brackets in the host component.
        $address = trim($host, '[]');
        $loopback = false;

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $loopback = str_starts_with($address, '127.');
        } elseif (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $loopback = strcasecmp($address, '::1') === 0;
        }

        return $loopback;
    }

    /** Build a safe error message from the last cURL error. */
    private static function curlFailure(CurlHandle $curl): string
    {
        return 'cURL error: ' . LogRedactor::text(curl_error($curl));
    }
}
