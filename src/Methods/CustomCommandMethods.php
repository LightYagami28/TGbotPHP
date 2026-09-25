<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Support\Value;

/**
 * Bot profile and command methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#setmycommands
 */
trait CustomCommandMethods
{
    use CallsApi;

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
        ?string $languageCode = null,
    ): bool {
        $list = [];
        foreach ($commands as $key => $value) {
            // A string value is a description: its key is the command, even when PHP made "2024" an integer key
            $list[] = is_string($value) ? ['command' => self::commandName((string) $key), 'description' => $value] : $value;
        }

        return $this->apiCallBool('setMyCommands', [
            'commands' => $list,
            'scope' => self::normalizeCommandScope($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * Get the list of bot commands
     *
     * @param array<string, mixed>|string|null $scope
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getmycommands
     */
    public function getMyCommands(
        array|string|null $scope = null,
        ?string $languageCode = null,
    ): array {
        return $this->apiCallList('getMyCommands', [
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
        ?string $languageCode = null,
    ): bool {
        return $this->apiCallBool('deleteMyCommands', [
            'scope' => self::normalizeCommandScope($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyname
     */
    public function setMyName(?string $name = null, ?string $languageCode = null): bool
    {
        return $this->apiCallBool('setMyName', [
            'name' => $name,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyname
     */
    public function getMyName(?string $languageCode = null): string
    {
        $result = $this->apiCallObject('getMyName', ['language_code' => $languageCode]);

        return Value::string($result['name'] ?? null);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmydescription
     */
    public function setMyDescription(?string $description = null, ?string $languageCode = null): bool
    {
        return $this->apiCallBool('setMyDescription', [
            'description' => $description,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmydescription
     */
    public function getMyDescription(?string $languageCode = null): string
    {
        $result = $this->apiCallObject('getMyDescription', ['language_code' => $languageCode]);

        return Value::string($result['description'] ?? null);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyshortdescription
     */
    public function setMyShortDescription(?string $shortDescription = null, ?string $languageCode = null): bool
    {
        return $this->apiCallBool('setMyShortDescription', [
            'short_description' => $shortDescription,
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyshortdescription
     */
    public function getMyShortDescription(?string $languageCode = null): string
    {
        $result = $this->apiCallObject('getMyShortDescription', ['language_code' => $languageCode]);

        return Value::string($result['short_description'] ?? null);
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
        return $this->apiCallBool('setChatMenuButton', [
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
        return $this->apiCallObject('getChatMenuButton', ['chat_id' => $chatId]);
    }

    /**
     * @param array<string, bool>|null $rights ChatAdministratorRights object
     *
     * @see https://core.telegram.org/bots/api#setmydefaultadministratorrights
     */
    public function setMyDefaultAdministratorRights(?array $rights = null, bool $forChannels = false): bool
    {
        return $this->apiCallBool('setMyDefaultAdministratorRights', [
            'rights' => $rights,
            'for_channels' => $forChannels ? true : null,
        ]);
    }

    /**
     * @return array<string, mixed> ChatAdministratorRights
     *
     * @see https://core.telegram.org/bots/api#getmydefaultadministratorrights
     */
    public function getMyDefaultAdministratorRights(bool $forChannels = false): array
    {
        return $this->apiCallObject('getMyDefaultAdministratorRights', [
            'for_channels' => $forChannels ? true : null,
        ]);
    }

    /**
     * Telegram only accepts lowercase command names, without the slash
     */
    private static function commandName(string $command): string
    {
        return strtolower(ltrim(trim($command), '/'));
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

    /**
     * Remove the profile photo of the bot
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#removemyprofilephoto
     */
    public function removeMyProfilePhoto(array $options = []): bool
    {
        return $this->apiCallBool('removeMyProfilePhoto', [], $options);
    }

    /**
     * Change the profile photo of the bot
     *
     * @param array<string, mixed> $photo
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setmyprofilephoto
     */
    public function setMyProfilePhoto(array $photo, array $options = []): bool
    {
        return $this->apiCallBool('setMyProfilePhoto', [
            'photo' => $photo,
        ], $options);
    }
}
