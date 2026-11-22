<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

/**
 * Raw HTTP response returned by a transport.
 *
 * The body is kept as a string so callers can decode JSON or treat it as
 * binary data depending on the endpoint.
 */
final class HttpResponse
{
    /**
     * @param int $statusCode HTTP status code reported by cURL
     * @param string $body Raw response body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
        // Value object: no body required.
    }
}
