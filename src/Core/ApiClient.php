<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Methods\AdminMethods;
use TGbotPHP\Methods\ChatMethods;
use TGbotPHP\Methods\CustomCommandMethods;
use TGbotPHP\Methods\ForumTopicMethods;
use TGbotPHP\Methods\GameMethods;
use TGbotPHP\Methods\InlineMethods;
use TGbotPHP\Methods\LocationMethods;
use TGbotPHP\Methods\MediaMethods;
use TGbotPHP\Methods\MessageMethods;
use TGbotPHP\Methods\PaymentMethods;
use TGbotPHP\Methods\ReactionMethods;
use TGbotPHP\Methods\StickerMethods;
use TGbotPHP\Methods\UpdateMethods;
use TGbotPHP\Methods\UserMethods;
use TGbotPHP\Traits\HttpClientTrait;

/**
 * Complete Telegram Bot API client
 *
 * Composes all method traits. Any method without a dedicated wrapper can be
 * invoked with call().
 */
class ApiClient
{
    use AdminMethods;
    use ChatMethods;
    use CustomCommandMethods;
    use ForumTopicMethods;
    use GameMethods;
    use InlineMethods;
    use LocationMethods;
    use MediaMethods;
    use MessageMethods;
    use PaymentMethods;
    use ReactionMethods;
    use StickerMethods;
    use UpdateMethods;
    use UserMethods;
    use HttpClientTrait;

    public const string VERSION = '3.0.0';

    protected readonly Config $config;

    public function __construct(Config $config, ?TransportInterface $transport = null)
    {
        $this->config = $config;

        if ($transport !== null) {
            $this->setTransport($transport);
        }
    }

    /**
     * Get bot configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Call any API method directly
     *
     * Supports methods not yet implemented as specific functions.
     *
     * @param array<string, mixed> $parameters
     * @see https://core.telegram.org/bots/api
     */
    public function call(string $method, array $parameters = []): mixed
    {
        return $this->apiCall($method, $parameters);
    }

    /**
     * Get Telegram API base URL (contains the token: keep it private)
     */
    public function getApiUrl(): string
    {
        return $this->config->apiBaseUrl . '/bot' . $this->config->token;
    }

    /**
     * Get bot token
     */
    public function getToken(): string
    {
        return $this->config->getToken();
    }

    /**
     * Get bot token for HTTP requests (protected access)
     */
    protected function getBotToken(): string
    {
        return $this->config->getToken();
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return Config::isValidToken($this->config->token);
    }
}
