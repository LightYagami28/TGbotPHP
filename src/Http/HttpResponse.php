<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

/**
 * Raw HTTP response returned by a transport
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {}
}
