<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;

/**
 * Game methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#games
 */
trait GameMethods
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

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendgame
     */
    public function sendGame(
        int $chatId,
        string $gameShortName,
        bool $disableNotification = false,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = []
    ): array {
        return $this->apiCallObject('sendGame', [
            'chat_id' => $chatId,
            'game_short_name' => $gameShortName,
            'disable_notification' => $disableNotification ? true : null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @return array<string, mixed>|bool Edited message, or true for inline messages
     *
     * @see https://core.telegram.org/bots/api#setgamescore
     */
    public function setGameScore(
        int $userId,
        int $score,
        bool $force = false,
        bool $disableEditMessage = false,
        ?int $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null
    ): array|bool {
        return $this->apiCallObjectOrTrue('setGameScore', [
            'user_id' => $userId,
            'score' => $score,
            'force' => $force ? true : null,
            'disable_edit_message' => $disableEditMessage ? true : null,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    /**
     * @return list<array<string, mixed>> GameHighScore objects
     *
     * @see https://core.telegram.org/bots/api#getgamehighscores
     */
    public function getGameHighScores(
        int $userId,
        ?int $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null
    ): array {
        return $this->apiCallList('getGameHighScores', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }
}
