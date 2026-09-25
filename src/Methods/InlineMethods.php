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
     */
    abstract protected function apiCall(string $method, array $params = [], array $options = []): mixed;

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
        return (bool) $this->apiCall('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ?: null,
            'url' => $url,
            'cache_time' => $cacheTime,
        ], $options);
    }

    /**
     * Answer inline query
     *
     * `$switchPmText`/`$switchPmParameter` are converted to the current
     * InlineQueryResultsButton format.
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#answerinlinequery
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        ?int $cacheTime = null,
        bool $isPersonal = false,
        ?string $nextOffset = null,
        ?string $switchPmText = null,
        ?string $switchPmParameter = null,
        array $options = []
    ): bool {
        $button = null;
        if ($switchPmText !== null) {
            $button = ['text' => $switchPmText, 'start_parameter' => $switchPmParameter ?? ''];
        }

        return (bool) $this->apiCall('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => array_values($results),
            'cache_time' => $cacheTime,
            'is_personal' => $isPersonal ?: null,
            'next_offset' => $nextOffset,
            'button' => $button,
        ], $options);
    }

    /**
     * Answer web app query
     *
     * @param array<string, mixed> $result InlineQueryResult
     * @return array<string, mixed> SentWebAppMessage
     *
     * @see https://core.telegram.org/bots/api#answerwebappquery
     */
    public function answerWebAppQuery(string $webAppQueryId, array $result): array
    {
        return $this->apiCall('answerWebAppQuery', [
            'web_app_query_id' => $webAppQueryId,
            'result' => $result,
        ]);
    }
}
