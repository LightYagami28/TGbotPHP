<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Support\Value;

/**
 * User, file and bot session methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait UserMethods
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    abstract protected function apiCallObject(string $method, array $params = [], array $options = []): array;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    abstract protected function apiCallList(string $method, array $params = [], array $options = []): array;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return array<string, mixed>|bool
     */
    abstract protected function apiCallObjectOrTrue(string $method, array $params = [], array $options = []): array|bool;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallBool(string $method, array $params = [], array $options = []): bool;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallInt(string $method, array $params = [], array $options = []): int;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCallString(string $method, array $params = [], array $options = []): string;

    abstract public function getTransport(): TransportInterface;

    /**
     * Get bot info
     *
     * @return array<string, mixed> User
     *
     * @see https://core.telegram.org/bots/api#getme
     */
    public function getMe(): array
    {
        return $this->apiCallObject('getMe');
    }

    /**
     * Log out from the cloud Bot API server before moving to a local one
     *
     * @see https://core.telegram.org/bots/api#logout
     */
    public function logOut(): bool
    {
        return $this->apiCallBool('logOut');
    }

    /**
     * Close the bot instance before moving it from one local server to another
     *
     * @see https://core.telegram.org/bots/api#close
     */
    public function close(): bool
    {
        return $this->apiCallBool('close');
    }

    /**
     * Get user profile photos
     *
     * @return array<string, mixed> UserProfilePhotos
     *
     * @see https://core.telegram.org/bots/api#getuserprofilephotos
     */
    public function getUserProfilePhotos(
        int $userId,
        ?int $offset = null,
        ?int $limit = null
    ): array {
        return $this->apiCallObject('getUserProfilePhotos', [
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Get the boosts added by a user to a chat
     *
     * @return array<string, mixed> UserChatBoosts
     *
     * @see https://core.telegram.org/bots/api#getuserchatboosts
     */
    public function getUserChatBoosts(int|string $chatId, int $userId): array
    {
        return $this->apiCallObject('getUserChatBoosts', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get file info
     *
     * @return array<string, mixed> File
     *
     * @see https://core.telegram.org/bots/api#getfile
     */
    public function getFile(string $fileId): array
    {
        return $this->apiCallObject('getFile', ['file_id' => $fileId]);
    }

    /**
     * Build the download URL for a file_path returned by getFile()
     *
     * The URL contains the bot token: never expose it to users.
     */
    public function getFileUrl(string $filePath): string
    {
        return $this->config->apiBaseUrl . '/file/bot' . $this->config->token . '/' . ltrim($filePath, '/');
    }

    /**
     * Download a file by file_id
     *
     * Returns the file contents, or writes them to $destination and returns the path.
     *
     * @throws ApiException
     */
    public function downloadFile(string $fileId, ?string $destination = null): string
    {
        $file = $this->getFile($fileId);

        $filePath = Value::nullableString($file['file_path'] ?? null);

        if ($filePath === null || $filePath === '') {
            throw new ApiException('File is not available for download', 0, $file, 'getFile');
        }

        $response = $this->getTransport()->get($this->getFileUrl($filePath), max(60, $this->config->timeout));

        if ($response->statusCode !== 200) {
            throw new ApiException("HTTP {$response->statusCode} while downloading file", $response->statusCode, [], 'getFile');
        }

        if ($destination === null) {
            return $response->body;
        }

        if (file_put_contents($destination, $response->body, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write file: $destination");
        }

        return $destination;
    }
}
