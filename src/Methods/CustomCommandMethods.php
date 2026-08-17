<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Custom bot command methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#my-commands
 */
trait CustomCommandMethods
{
    use HttpClientTrait;

    /**
     * Change bot commands
     *
     * @see https://core.telegram.org/bots/api#setmycommands
     */
    public function setMyCommands(
        array $commands,
        string|null $scope = null,
        string|null $languageCode = null
    ): bool {
        $result = $this->httpRequest('setMyCommands', [
            'commands' => json_encode($commands),
            'scope' => $scope,
            'language_code' => $languageCode,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Get bot commands
     *
     * @see https://core.telegram.org/bots/api#getmycommands
     */
    public function getMyCommands(
        string|null $scope = null,
        string|null $languageCode = null
    ): array|null {
        return $this->httpRequest('getMyCommands', [
            'scope' => $scope,
            'language_code' => $languageCode,
        ], returnResponse: true);
    }

    /**
     * Delete bot commands
     *
     * @see https://core.telegram.org/bots/api#deletemycommands
     */
    public function deleteMyCommands(
        string|null $scope = null,
        string|null $languageCode = null
    ): bool {
        $result = $this->httpRequest('deleteMyCommands', [
            'scope' => $scope,
            'language_code' => $languageCode,
        ], returnResponse: true);

        return $result !== null;
    }
}
