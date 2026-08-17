<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Inline mode methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#inline-mode
 */
trait InlineMethods
{
    use HttpClientTrait;

    /**
     * Answer inline query
     *
     * @see https://core.telegram.org/bots/api#answerinlinequery
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        int|null $cacheTime = null,
        bool $isPersonal = false,
        string|null $nextOffset = null,
        string|null $switchPmText = null,
        string|null $switchPmParameter = null
    ): bool {
        $result = $this->httpRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results),
            'cache_time' => $cacheTime,
            'is_personal' => $isPersonal ? 'true' : 'false',
            'next_offset' => $nextOffset,
            'switch_pm_text' => $switchPmText,
            'switch_pm_parameter' => $switchPmParameter,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Answer web app query
     *
     * @see https://core.telegram.org/bots/api#answerwebappquery
     */
    public function answerWebAppQuery(
        string $webAppQueryId,
        array $result
    ): array|null {
        return $this->httpRequest('answerWebAppQuery', [
            'web_app_query_id' => $webAppQueryId,
            'result' => json_encode($result),
        ], returnResponse: true);
    }
}
