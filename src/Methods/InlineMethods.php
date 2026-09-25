<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Inline mode and callback query methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#inline-mode
 */
trait InlineMethods
{
    use CallsApi;

    /**
     * Answer a callback query sent from an inline keyboard button
     *
     * Must be called for every callback query, otherwise the client keeps
     * showing a progress indicator.
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#answercallbackquery
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
        ?string $url = null,
        ?int $cacheTime = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? true : null,
            'url' => $url,
            'cache_time' => $cacheTime,
        ], $options);
    }

    /**
     * @param list<array<string, mixed>> $results
     * @param array<string, mixed> $options button (InlineQueryResultsButton)...
     *
     * @see https://core.telegram.org/bots/api#answerinlinequery
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        ?int $cacheTime = null,
        bool $isPersonal = false,
        ?string $nextOffset = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => $results,
            'cache_time' => $cacheTime,
            'is_personal' => $isPersonal ? true : null,
            'next_offset' => $nextOffset,
        ], $options);
    }

    /**
     * @param array<string, mixed> $result InlineQueryResult
     * @return array<string, mixed> SentWebAppMessage
     *
     * @see https://core.telegram.org/bots/api#answerwebappquery
     */
    public function answerWebAppQuery(string $webAppQueryId, array $result): array
    {
        return $this->apiCallObject('answerWebAppQuery', [
            'web_app_query_id' => $webAppQueryId,
            'result' => $result,
        ]);
    }

    /**
     * Reply to a received guest message
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#answerguestquery
     */
    public function answerGuestQuery(
        string $guestQueryId,
        array $result,
        array $options = [],
    ): array {
        return $this->apiCallObject('answerGuestQuery', [
            'guest_query_id' => $guestQueryId,
            'result' => $result,
        ], $options);
    }

    /**
     * Store a message that can be sent by a user of a Mini App
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options allow_user_chats, allow_bot_chats, allow_group_chats, allow_channel_chats
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#savepreparedinlinemessage
     */
    public function savePreparedInlineMessage(
        int $userId,
        array $result,
        array $options = [],
    ): array {
        return $this->apiCallObject('savePreparedInlineMessage', [
            'user_id' => $userId,
            'result' => $result,
        ], $options);
    }

    /**
     * Store a keyboard button that can be used by a user within a Mini App
     *
     * @param array<string, mixed> $button
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#savepreparedkeyboardbutton
     */
    public function savePreparedKeyboardButton(
        int $userId,
        array $button,
        array $options = [],
    ): array {
        return $this->apiCallObject('savePreparedKeyboardButton', [
            'user_id' => $userId,
            'button' => $button,
        ], $options);
    }
}
