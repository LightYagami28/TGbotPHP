<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Payment methods from Telegram Bot API
 *
 * For payments in Telegram Stars use currency "XTR" and omit the provider token.
 *
 * @see https://core.telegram.org/bots/api#payments
 */
trait PaymentMethods
{
    use CallsApi;

    /**
     * For payments in Telegram Stars use the "XTR" currency and no provider token
     *
     * @param list<array{label: string, amount: int}> $prices
     * @param array<string, mixed> $options provider_token, max_tip_amount, suggested_tip_amounts, photo_url...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendinvoice
     */
    public function sendInvoice(
        int|string $chatId,
        string $title,
        string $description,
        string $payload,
        string $currency,
        array $prices,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendInvoice', [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => $currency,
            'prices' => $prices,
        ], $options);
    }

    /**
     * @param array<int, array{label: string, amount: int}> $prices
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#createinvoicelink
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payload,
        string $currency,
        array $prices,
        ?string $providerToken = null,
        array $options = [],
    ): string {
        return $this->apiCallString('createInvoiceLink', [
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => $currency,
            'prices' => array_values($prices),
            'provider_token' => $providerToken,
        ], $options);
    }

    /**
     * @param array<int, array<string, mixed>>|null $shippingOptions
     *
     * @see https://core.telegram.org/bots/api#answershippingquery
     */
    public function answerShippingQuery(
        string $shippingQueryId,
        bool $ok,
        ?array $shippingOptions = null,
        ?string $errorMessage = null,
    ): bool {
        return $this->apiCallBool('answerShippingQuery', [
            'shipping_query_id' => $shippingQueryId,
            'ok' => $ok,
            'shipping_options' => $shippingOptions,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Answer pre-checkout query (must be answered within 10 seconds)
     *
     * @see https://core.telegram.org/bots/api#answerprecheckoutquery
     */
    public function answerPreCheckoutQuery(
        string $preCheckoutQueryId,
        bool $ok,
        ?string $errorMessage = null,
    ): bool {
        return $this->apiCallBool('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Refund a successful payment in Telegram Stars
     *
     * @see https://core.telegram.org/bots/api#refundstarpayment
     */
    public function refundStarPayment(int $userId, string $telegramPaymentChargeId): bool
    {
        return $this->apiCallBool('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
        ]);
    }

    /**
     * Get the bot's Telegram Star transactions
     *
     * @return array<string, mixed> StarTransactions
     *
     * @see https://core.telegram.org/bots/api#getstartransactions
     */
    public function getStarTransactions(?int $offset = null, ?int $limit = null): array
    {
        return $this->apiCallObject('getStarTransactions', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Cancel or re-enable extension of a subscription paid in Telegram Stars
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#edituserstarsubscription
     */
    public function editUserStarSubscription(
        int $userId,
        string $telegramPaymentChargeId,
        bool $isCanceled,
        array $options = [],
    ): bool {
        return $this->apiCallBool('editUserStarSubscription', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
            'is_canceled' => $isCanceled,
        ], $options);
    }

    /**
     * Get the current Telegram Stars balance of the bot
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#getmystarbalance
     */
    public function getMyStarBalance(array $options = []): array
    {
        return $this->apiCallObject('getMyStarBalance', [], $options);
    }
}
