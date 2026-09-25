<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

use TGbotPHP\Exceptions\NetworkException;

/**
 * HTTP transport used to talk to the Telegram Bot API
 *
 * Implement this interface to plug in a custom HTTP client or a fake one in tests.
 */
interface TransportInterface
{
    /**
     * Send a POST request
     *
     * @param array<string, mixed> $fields Prepared fields (scalars and CURLFile instances)
     * @param bool $multipart Whether the fields contain files and must be sent as multipart/form-data
     *
     * @throws NetworkException
     */
    public function post(string $url, array $fields, bool $multipart, int $timeout): HttpResponse;

    /**
     * Send a GET request (used to download files)
     *
     * @throws NetworkException
     */
    public function get(string $url, int $timeout): HttpResponse;
}
