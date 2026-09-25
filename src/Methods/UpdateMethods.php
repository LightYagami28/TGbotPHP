<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Types\InputFile;

/**
 * Update handling methods from Telegram Bot API
 *
 * Supports both webhook and long polling modes.
 * @see https://core.telegram.org/bots/api#getting-updates
 */
trait UpdateMethods
{
    use CallsApi;

    /**
     * Receive incoming updates using long polling
     *
     * @param string[]|null $allowedUpdates
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getupdates
     */
    public function getUpdates(
        ?int $offset = null,
        ?int $limit = null,
        ?int $timeout = null,
        ?array $allowedUpdates = null,
    ): array {
        return $this->apiCallList('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
            'allowed_updates' => $allowedUpdates !== null ? array_values($allowedUpdates) : null,
        ]);
    }

    /**
     * Set webhook URL for receiving updates
     *
     * @param string[]|null $allowedUpdates
     *
     * @see https://core.telegram.org/bots/api#setwebhook
     */
    public function setWebhook(
        string $url,
        ?string $ipAddress = null,
        ?int $maxConnections = null,
        ?array $allowedUpdates = null,
        bool $dropPendingUpdates = false,
        ?string $secretToken = null,
        ?InputFile $certificate = null,
    ): bool {
        if ($url !== '' && !str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('Webhook URL must use HTTPS');
        }

        return $this->apiCallBool('setWebhook', [
            'url' => $url,
            'certificate' => $certificate,
            'ip_address' => $ipAddress,
            'max_connections' => $maxConnections,
            'allowed_updates' => $allowedUpdates !== null ? array_values($allowedUpdates) : null,
            'drop_pending_updates' => $dropPendingUpdates ? true : null,
            'secret_token' => $secretToken,
        ]);
    }

    /**
     * Remove webhook integration; switch back to getUpdates
     *
     * @see https://core.telegram.org/bots/api#deletewebhook
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return $this->apiCallBool('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates ? true : null,
        ]);
    }

    /**
     * Get current webhook status and information
     *
     * @return array<string, mixed> WebhookInfo
     *
     * @see https://core.telegram.org/bots/api#getwebhookinfo
     */
    public function getWebhookInfo(): array
    {
        return $this->apiCallObject('getWebhookInfo');
    }
}
