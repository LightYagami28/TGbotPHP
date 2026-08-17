<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

/**
 * Update handling methods from Telegram Bot API
 *
 * Supports both webhook and long polling modes.
 * @see https://core.telegram.org/bots/api#getting-updates
 */
trait UpdateMethods
{
    use HttpClientTrait;

    /**
     * Receive incoming updates using long polling
     *
     * @see https://core.telegram.org/bots/api#getupdates
     */
    public function getUpdates(
        int|null $offset = null,
        int|null $limit = null,
        int|null $timeout = null,
        array|null $allowedUpdates = null
    ): array|null {
        return $this->httpRequest('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
            'allowed_updates' => $allowedUpdates ? json_encode($allowedUpdates) : null,
        ], returnResponse: true);
    }

    /**
     * Set webhook URL for receiving updates
     *
     * @see https://core.telegram.org/bots/api#setwebhook
     */
    public function setWebhook(
        string $url,
        string|null $ipAddress = null,
        int|null $maxConnections = null,
        array|null $allowedUpdates = null,
        bool $dropPendingUpdates = false,
        string|null $secretToken = null
    ): bool {
        $result = $this->httpRequest('setWebhook', [
            'url' => $url,
            'ip_address' => $ipAddress,
            'max_connections' => $maxConnections,
            'allowed_updates' => $allowedUpdates ? json_encode($allowedUpdates) : null,
            'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
            'secret_token' => $secretToken,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Remove webhook integration; switch back to getUpdates
     *
     * @see https://core.telegram.org/bots/api#deletewebhook
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        $result = $this->httpRequest('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Get current webhook status and information
     *
     * @see https://core.telegram.org/bots/api#getwebhookinfo
     */
    public function getWebhookInfo(): array|null
    {
        return $this->httpRequest('getWebhookInfo', [], returnResponse: true);
    }
}
