<?php

declare(strict_types=1);

namespace TGbotPHP\Http;

use TGbotPHP\Exceptions\NetworkException;

/**
 * HTTP transport used to talk to the Telegram Bot API.
 *
 * Implementing this interface is enough to plug in a custom HTTP client or a
 * fake one in tests; the rest of the library only depends on these three
 * operations.
 */
interface TransportInterface
{
    /**
     * Send a POST request.
     *
     * @param string $url Full URL, including the bot token
     * @param array<string, mixed> $fields Prepared fields (scalars and CURLFile instances)
     * @param bool $multipart Whether the fields contain files and must be sent as multipart/form-data
     * @param int $timeout Request timeout in seconds
     *
     * @throws NetworkException When the request cannot be completed
     */
    public function post(string $url, array $fields, bool $multipart, int $timeout): HttpResponse;

    /**
     * Send a GET request, used to download files.
     *
     * @param string $url Full file URL, including the bot token
     * @param int $timeout Request timeout in seconds
     *
     * @throws NetworkException When the request cannot be completed
     */
    public function get(string $url, int $timeout): HttpResponse;

    /**
     * Send a GET request and write the response body to a file.
     *
     * @param string $url Full file URL, including the bot token
     * @param string $destination Path of the file to create
     * @param int $timeout Request timeout in seconds
     *
     * @return int The HTTP status code
     *
     * @throws NetworkException When the file cannot be opened or the request fails
     */
    public function download(string $url, string $destination, int $timeout): int;
}
