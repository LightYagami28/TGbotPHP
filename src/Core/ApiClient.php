<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

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
 * Composes all method traits to provide complete 120+ method coverage.
 * Supports webhook and long polling modes.
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

    protected readonly Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
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
     * @see https://core.telegram.org/bots/api
     */
    public function call(string $method, array $parameters = []): array|null
    {
        return $this->httpRequest($method, $parameters, returnResponse: true);
    }

    /**
     * Get Telegram API base URL
     */
    public function getApiUrl(): string
    {
        return "https://api.telegram.org/bot{$this->config->token}";
    }

    /**
     * Get bot token
     */
    public function getToken(): string
    {
        return $this->config->token;
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return !empty($this->config->token) && strlen($this->config->token) >= 10;
    }
}
