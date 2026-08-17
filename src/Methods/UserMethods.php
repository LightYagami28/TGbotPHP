<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * User-related methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#available-methods
 */
trait UserMethods
{
    use HttpClientTrait;

    /**
     * Get bot info
     *
     * @see https://core.telegram.org/bots/api#getme
     */
    public function getMe(): array|null
    {
        return $this->httpRequest('getMe', [], returnResponse: true);
    }

    /**
     * Get user profile photos
     *
     * @see https://core.telegram.org/bots/api#getuserprofilephotos
     */
    public function getUserProfilePhotos(
        int $userId,
        int|null $offset = null,
        int|null $limit = null
    ): array|null {
        return $this->httpRequest('getUserProfilePhotos', [
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ], returnResponse: true);
    }

    /**
     * Get file info
     *
     * @see https://core.telegram.org/bots/api#getfile
     */
    public function getFile(string $fileId): array|null
    {
        return $this->httpRequest('getFile', [
            'file_id' => $fileId,
        ], returnResponse: true);
    }
}
