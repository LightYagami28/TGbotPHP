<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Game methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#games
 */
trait GameMethods
{
    use HttpClientTrait;

    /**
     * Send game
     *
     * @see https://core.telegram.org/bots/api#sendgame
     */
    public function sendGame(
        int $chatId,
        string $gameShortName,
        bool $disableNotification = false,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendGame', [
            'chat_id' => $chatId,
            'game_short_name' => $gameShortName,
            'disable_notification' => $disableNotification ? 'true' : 'false',
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    /**
     * Set game score
     *
     * @see https://core.telegram.org/bots/api#setgamescore
     */
    public function setGameScore(
        int $userId,
        int $score,
        bool $force = false,
        bool $disableEditMessage = false,
        int|null $chatId = null,
        int|null $messageId = null,
        string|null $inlineMessageId = null
    ): array|null {
        return $this->httpRequest('setGameScore', [
            'user_id' => $userId,
            'score' => $score,
            'force' => $force ? 'true' : 'false',
            'disable_edit_message' => $disableEditMessage ? 'true' : 'false',
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ], returnResponse: true);
    }

    /**
     * Get game high scores
     *
     * @see https://core.telegram.org/bots/api#getgamehighscores
     */
    public function getGameHighScores(
        int $userId,
        int|null $chatId = null,
        int|null $messageId = null,
        string|null $inlineMessageId = null
    ): array|null {
        return $this->httpRequest('getGameHighScores', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_message_id' => $inlineMessageId,
        ], returnResponse: true);
    }
}
