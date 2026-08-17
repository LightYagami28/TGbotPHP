<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Payment methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#payments
 */
trait PaymentMethods
{
    use HttpClientTrait;

    /**
     * Send invoice
     *
     * @see https://core.telegram.org/bots/api#sendinvoice
     */
    public function sendInvoice(
        int $chatId,
        string $title,
        string $description,
        string $payload,
        string $currency,
        array $prices,
        string|null $providerToken = null,
        int|null $maxTipAmount = null,
        array|null $suggestedTipAmounts = null
    ): array|null {
        return $this->httpRequest('sendInvoice', [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => $currency,
            'prices' => json_encode($prices),
            'provider_token' => $providerToken,
            'max_tip_amount' => $maxTipAmount,
            'suggested_tip_amounts' => $suggestedTipAmounts ? json_encode($suggestedTipAmounts) : null,
        ], returnResponse: true);
    }

    /**
     * Answer shipping query
     *
     * @see https://core.telegram.org/bots/api#answershippingquery
     */
    public function answerShippingQuery(
        string $shippingQueryId,
        bool $ok,
        array|null $shippingOptions = null,
        string|null $errorMessage = null
    ): bool {
        $result = $this->httpRequest('answerShippingQuery', [
            'shipping_query_id' => $shippingQueryId,
            'ok' => $ok ? 'true' : 'false',
            'shipping_options' => $shippingOptions ? json_encode($shippingOptions) : null,
            'error_message' => $errorMessage,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Answer pre-checkout query
     *
     * @see https://core.telegram.org/bots/api#answerprecheckoutquery
     */
    public function answerPreCheckoutQuery(
        string $preCheckoutQueryId,
        bool $ok,
        string|null $errorMessage = null
    ): bool {
        $result = $this->httpRequest('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok ? 'true' : 'false',
            'error_message' => $errorMessage,
        ], returnResponse: true);

        return $result !== null;
    }
}
