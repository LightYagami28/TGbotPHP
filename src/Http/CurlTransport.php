<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

use CurlHandle;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Exceptions\NetworkException;

/**
 * cURL based transport
 */
final class CurlTransport implements TransportInterface
{
    private ?CurlHandle $handle = null;

    public function __construct(private readonly int $connectTimeout = 5, private readonly int $maxResponseBytes = 20971520)
    {
        if ($connectTimeout < 1 || $maxResponseBytes < 1) {
            throw new \InvalidArgumentException('Transport timeout and response limit must be positive');
        }
    }

    #[\Override]
    public function post(string $url, array $fields, bool $multipart, int $timeout): HttpResponse
    {
        $options = [
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => $timeout,
        ];

        if ($multipart) {
            // Passing an array makes cURL build a multipart/form-data body with the proper boundary
            $options[CURLOPT_POSTFIELDS] = $fields;
        } else {
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields);
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
        }

        return $this->execute($url, $options);
    }

    #[\Override]
    public function get(string $url, int $timeout): HttpResponse
    {
        return $this->execute($url, [
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
    }

    #[\Override]
    public function download(string $url, string $destination, int $timeout): int
    {
        $file = @fopen($destination, 'wb');

        if ($file === false) {
            throw new NetworkException("Unable to open $destination for writing");
        }

        try {
            $curl = $this->prepare($url, [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FILE => $file,
            ]);

            if (curl_exec($curl) !== true) {
                throw new NetworkException('cURL download failed (code ' . curl_errno($curl) . ')', curl_errno($curl));
            }

            return curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } finally {
            fclose($file);
        }
    }

    /**
     * @param array<int, mixed> $options
     */
    private function execute(string $url, array $options): HttpResponse
    {
        $body = '';
        $curl = $this->prepare($url, $options + [
            CURLOPT_WRITEFUNCTION => function (CurlHandle $handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > $this->maxResponseBytes) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        if (curl_exec($curl) !== true) {
            throw new NetworkException('cURL request failed or response limit exceeded (code ' . curl_errno($curl) . ')', curl_errno($curl));
        }

        return new HttpResponse(curl_getinfo($curl, CURLINFO_HTTP_CODE), $body);
    }

    /**
     * The handle is reused, so consecutive requests keep the connection (and the TLS session) open
     *
     * @param array<int, mixed> $options
     */
    private function prepare(string $url, array $options): CurlHandle
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
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
            // Never follow redirects or switch to another protocol (file://, gopher://, ...)
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_USERAGENT => 'TGbotPHP/' . ApiClient::VERSION,
        ]);

        return $this->handle;
    }
}
