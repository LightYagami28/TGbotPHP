<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Bot profile and command methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#setmycommands
 */
trait CustomCommandMethods
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * Change the list of bot commands
     *
     * Commands may be given as a list of BotCommand objects
     * (`[['command' => 'start', 'description' => 'Start']]`) or as a
     * `['start' => 'Start the bot']` map.
     *
     * @param array<int|string, mixed> $commands
     * @param array<string, mixed>|string|null $scope BotCommandScope object or its type ("all_private_chats", ...)
     *
     * @see https://core.telegram.org/bots/api#setmycommands
     */
    public function setMyCommands(
        array $commands,
        array|string|null $scope = null,
        ?string $languageCode = null
    ): bool {
        $list = [];
        foreach ($commands as $key => $value) {
            $list[] = is_string($key) ? ['command' => ltrim($key, '/'), 'description' => (string) $value] : $value;
        }

        return (bool) $this->apiCall('setMyCommands', [
            'commands' => $list,
            'scope' => self::normalizeCommandScope($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * Get the list of bot commands
     *
     * @param array<string, mixed>|string|null $scope
     * @return array<int, array<string, string>>
     *
     * @see https://core.telegram.org/bots/api#getmycommands
     */
    public function getMyCommands(
        array|string|null $scope = null,
        ?string $languageCode = null
    ): array {
        return $this->apiCall('getMyCommands', [
            'scope' => self::normalizeCommandScope($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * Delete the list of bot commands
     *
     * @param array<string, mixed>|string|null $scope
     *
     * @see https://core.telegram.org/bots/api#deletemycommands
     */
    public function deleteMyCommands(
        array|string|null $scope = null,
        ?string $languageCode = null
    ): bool {
        return (bool) $this->apiCall('deleteMyCommands', [
            'scope' => self::normalizeCommandScope($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyname
     */
    public function setMyName(?string $name = null, ?string $languageCode = null): bool
    {
        return (bool) $this->apiCall('setMyName', [
            'name' => $name,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyname
     */
    public function getMyName(?string $languageCode = null): string
    {
        $result = $this->apiCall('getMyName', ['language_code' => $languageCode]);

        return (string) ($result['name'] ?? '');
    }

    /**
     * @see https://core.telegram.org/bots/api#setmydescription
     */
    public function setMyDescription(?string $description = null, ?string $languageCode = null): bool
    {
        return (bool) $this->apiCall('setMyDescription', [
            'description' => $description,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmydescription
     */
    public function getMyDescription(?string $languageCode = null): string
    {
        $result = $this->apiCall('getMyDescription', ['language_code' => $languageCode]);

        return (string) ($result['description'] ?? '');
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyshortdescription
     */
    public function setMyShortDescription(?string $shortDescription = null, ?string $languageCode = null): bool
    {
        return (bool) $this->apiCall('setMyShortDescription', [
            'short_description' => $shortDescription,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyshortdescription
     */
    public function getMyShortDescription(?string $languageCode = null): string
    {
        $result = $this->apiCall('getMyShortDescription', ['language_code' => $languageCode]);

        return (string) ($result['short_description'] ?? '');
    }

    /**
     * Change the bot's menu button in a private chat, or the default one
     *
     * @param array<string, mixed>|null $menuButton MenuButton object
     *
     * @see https://core.telegram.org/bots/api#setchatmenubutton
     */
    public function setChatMenuButton(?int $chatId = null, ?array $menuButton = null): bool
    {
        return (bool) $this->apiCall('setChatMenuButton', [
            'chat_id' => $chatId,
            'menu_button' => $menuButton,
        ]);
    }

    /**
     * @return array<string, mixed> MenuButton
     *
     * @see https://core.telegram.org/bots/api#getchatmenubutton
     */
    public function getChatMenuButton(?int $chatId = null): array
    {
        return $this->apiCall('getChatMenuButton', ['chat_id' => $chatId]);
    }

    /**
     * @param array<string, bool>|null $rights ChatAdministratorRights object
     *
     * @see https://core.telegram.org/bots/api#setmydefaultadministratorrights
     */
    public function setMyDefaultAdministratorRights(?array $rights = null, bool $forChannels = false): bool
    {
        return (bool) $this->apiCall('setMyDefaultAdministratorRights', [
            'rights' => $rights,
            'for_channels' => $forChannels ?: null,
        ]);
    }

    /**
     * @return array<string, bool> ChatAdministratorRights
     *
     * @see https://core.telegram.org/bots/api#getmydefaultadministratorrights
     */
    public function getMyDefaultAdministratorRights(bool $forChannels = false): array
    {
        return $this->apiCall('getMyDefaultAdministratorRights', [
            'for_channels' => $forChannels ?: null,
        ]);
    }

    /**
     * @param array<string, mixed>|string|null $scope
     * @return array<string, mixed>|string|null
     */
    private static function normalizeCommandScope(array|string|null $scope): array|string|null
    {
        if (is_string($scope) && !str_starts_with(ltrim($scope), '{')) {
            return ['type' => $scope];
        }

        return $scope;
    }
}
