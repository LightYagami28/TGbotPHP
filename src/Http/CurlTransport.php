<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

use TGbotPHP\Core\ApiClient;
use TGbotPHP\Exceptions\NetworkException;

/**
 * cURL based transport
 */
final class CurlTransport implements TransportInterface
{
    public function __construct(private readonly int $connectTimeout = 5)
    {
    }

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

    public function get(string $url, int $timeout): HttpResponse
    {
        return $this->execute($url, [
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
    }

    /**
     * @param array<int, mixed> $options
     */
    private function execute(string $url, array $options): HttpResponse
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TGbotPHP/' . ApiClient::VERSION,
        ]);

        $body = curl_exec($curl);

        if (!is_string($body)) {
            throw new NetworkException('cURL error: ' . curl_error($curl), curl_errno($curl));
        }

        return new HttpResponse((int) curl_getinfo($curl, CURLINFO_HTTP_CODE), $body);
    }
}
