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
        array $options = []
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
        array $options = []
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
}
