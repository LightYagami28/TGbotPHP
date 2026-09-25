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
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

    /**
     * Send game
     *
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
        return $this->apiCall('sendGame', [
            'chat_id' => $chatId,
            'game_short_name' => $gameShortName,
            'disable_notification' => $disableNotification ?: null,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * Set game score
     *
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
        return $this->apiCall('setGameScore', [
            'user_id' => $userId,
            'score' => $score,
            'force' => $force ?: null,
            'disable_edit_message' => $disableEditMessage ?: null,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    /**
     * Get game high scores
     *
     * @return array<int, array<string, mixed>> GameHighScore objects
     *
     * @see https://core.telegram.org/bots/api#getgamehighscores
     */
    public function getGameHighScores(
        int $userId,
        ?int $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null
    ): array {
        return $this->apiCall('getGameHighScores', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }
}
