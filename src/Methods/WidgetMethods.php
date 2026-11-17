<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Interactive HTML Widget methods from Telegram Bot API 10.4
 *
 * Send interactive widgets, update widget state, and answer widget queries.
 *
 * @see https://core.telegram.org/bots/api#sendwidget
 */
trait WidgetMethods
{
    use CallsApi;

    /**
     * Send an interactive HTML widget
     *
     * @param int|string $chatId Unique identifier for the target chat
     * @param string $html The HTML content of the widget
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendwidget
     */
    public function sendWidget(int|string $chatId, string $html, array $options = []): array
    {
        return $this->apiCallObject('sendWidget', [
            'chat_id' => $chatId,
            'html' => $html,
        ], $options);
    }

    /**
     * Send a shared widget created by the bot
     *
     * @param int|string $chatId Unique identifier for the target chat
     * @param string $widgetId Unique identifier of the shared widget
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendsharedwidget
     */
    public function sendSharedWidget(int|string $chatId, string $widgetId, array $options = []): array
    {
        return $this->apiCallObject('sendSharedWidget', [
            'chat_id' => $chatId,
            'widget_id' => $widgetId,
        ], $options);
    }

    /**
     * Send an answer to a query sent by an interactive widget
     *
     * @param string $widgetQueryId Unique identifier for the query to be answered
     * @param array<string, mixed> $options data
     * @return bool
     *
     * @see https://core.telegram.org/bots/api#answerwidgetquery
     */
    public function answerWidgetQuery(string $widgetQueryId, array $options = []): bool
    {
        return $this->apiCallBool('answerWidgetQuery', [
            'widget_query_id' => $widgetQueryId,
        ], $options);
    }

    /**
     * Edit the data of a widget in a message
     *
     * @param array<string, mixed> $options chat_id, message_id, inline_message_id, public_data, chat_data
     * @return bool
     *
     * @see https://core.telegram.org/bots/api#editmessagewidgetdata
     */
    public function editMessageWidgetData(array $options = []): bool
    {
        return $this->apiCallBool('editMessageWidgetData', [], $options);
    }

    /**
     * Edit the public data of a shared widget
     *
     * @param string $widgetId Unique identifier of the shared widget
     * @param string $publicData New public data for the widget
     * @param array<string, mixed> $options cache_time
     * @return bool
     *
     * @see https://core.telegram.org/bots/api#editsharedwidgetdata
     */
    public function editSharedWidgetData(string $widgetId, string $publicData, array $options = []): bool
    {
        return $this->apiCallBool('editSharedWidgetData', [
            'widget_id' => $widgetId,
            'public_data' => $publicData,
        ], $options);
    }
}
