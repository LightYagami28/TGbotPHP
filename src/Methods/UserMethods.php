<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Core\Config;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Support\Value;

/**
 * User, file and bot session methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait UserMethods
{
    use CallsApi;

    /** Largest file downloadFile() loads in memory: the Bot API download limit */
    public const int MAX_MEMORY_DOWNLOAD = 20 * 1024 * 1024;

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
     * @return array<string, mixed> UserProfilePhotos
     *
     * @see https://core.telegram.org/bots/api#getuserprofilephotos
     */
    public function getUserProfilePhotos(
        int $userId,
        ?int $offset = null,
        ?int $limit = null,
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
     * Without $destination the contents are returned, for files up to
     * MAX_MEMORY_DOWNLOAD bytes. With $destination the file is streamed to
     * disk, whatever its size, and the path is returned. The destination only
     * appears once the download is complete.
     *
     * With a local Bot API server (--local), getFile returns a path on the
     * server's disk: the file is copied from there.
     *
     * @throws ApiException
     * @throws StorageException
     */
    public function downloadFile(string $fileId, ?string $destination = null): string
    {
        $file = $this->getFile($fileId);

        $filePath = Value::nullableString($file['file_path'] ?? null);

        if ($filePath === null || $filePath === '') {
            throw new ApiException('File is not available for download', 0, $file, 'getFile');
        }

        $size = Value::nullableInt($file['file_size'] ?? null);

        if ($destination === null && $size !== null && $size > self::MAX_MEMORY_DOWNLOAD) {
            throw new StorageException("File is too large to load in memory ($size bytes): pass a destination");
        }

        if ($this->isLocalServerFile($filePath)) {
            return $this->copyLocalFile($filePath, $destination);
        }

        $url = $this->getFileUrl($filePath);
        $timeout = max(60, $this->config->timeout);

        if ($destination === null) {
            $response = $this->getTransport()->get($url, $timeout);
            self::checkDownloadStatus($response->statusCode);

            return $response->body;
        }

        $tmp = $destination . '.' . bin2hex(random_bytes(4)) . '.part';

        try {
            self::checkDownloadStatus($this->getTransport()->download($url, $tmp, $timeout));

            if (!@rename($tmp, $destination)) {
                throw new StorageException("Unable to write file: $destination");
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $destination;
    }

    /**
     * Absolute paths only come from a local Bot API server, which shares its disk with the bot
     */
    private function isLocalServerFile(string $filePath): bool
    {
        return $this->config->apiBaseUrl !== Config::DEFAULT_API_URL
            && str_starts_with($filePath, '/')
            && is_file($filePath);
    }

    private function copyLocalFile(string $filePath, ?string $destination): string
    {
        if ($destination === null) {
            $contents = @file_get_contents($filePath);

            if ($contents === false) {
                throw new StorageException("Unable to read file: $filePath");
            }

            return $contents;
        }

        if (!@copy($filePath, $destination)) {
            throw new StorageException("Unable to write file: $destination");
        }

        return $destination;
    }

    private static function checkDownloadStatus(int $statusCode): void
    {
        if ($statusCode !== 200) {
            throw new ApiException("HTTP $statusCode while downloading file", $statusCode, [], 'getFile');
        }
    }

    /**
     * Get a list of profile audios for a user
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getuserprofileaudios
     */
    public function getUserProfileAudios(
        int $userId,
        ?int $offset = null,
        ?int $limit = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('getUserProfileAudios', [
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ], $options);
    }

    /**
     * Tell a user that some of the Telegram Passport elements they provided contain errors
     *
     * @param list<array<string, mixed>> $errors
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setpassportdataerrors
     */
    public function setPassportDataErrors(
        int $userId,
        array $errors,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setPassportDataErrors', [
            'user_id' => $userId,
            'errors' => $errors,
        ], $options);
    }

    /**
     * Change the emoji status of a user who allowed the bot to manage it (requestEmojiStatusAccess in Mini Apps)
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setuseremojistatus
     */
    public function setUserEmojiStatus(
        int $userId,
        ?string $emojiStatusCustomEmojiId = null,
        ?int $emojiStatusExpirationDate = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setUserEmojiStatus', [
            'user_id' => $userId,
            'emoji_status_custom_emoji_id' => $emojiStatusCustomEmojiId,
            'emoji_status_expiration_date' => $emojiStatusExpirationDate,
        ], $options);
    }
}
