<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Methods\AdminMethods;
use TGbotPHP\Methods\BusinessMethods;
use TGbotPHP\Methods\ChatMethods;
use TGbotPHP\Methods\CustomCommandMethods;
use TGbotPHP\Methods\EphemeralMessageMethods;
use TGbotPHP\Methods\ForumTopicMethods;
use TGbotPHP\Methods\GameMethods;
use TGbotPHP\Methods\GiftMethods;
use TGbotPHP\Methods\InlineMethods;
use TGbotPHP\Methods\LocationMethods;
use TGbotPHP\Methods\ManagedBotMethods;
use TGbotPHP\Methods\MediaMethods;
use TGbotPHP\Methods\MessageMethods;
use TGbotPHP\Methods\PaymentMethods;
use TGbotPHP\Methods\ReactionMethods;
use TGbotPHP\Methods\StickerMethods;
use TGbotPHP\Methods\StoryMethods;
use TGbotPHP\Methods\UpdateMethods;
use TGbotPHP\Methods\UserMethods;
use TGbotPHP\Methods\VerificationMethods;
use TGbotPHP\Methods\WidgetMethods;
use TGbotPHP\Traits\HttpClientTrait;

/**
 * Complete Telegram Bot API client.
 *
 * The concrete API methods come from the method traits; any method without a
 * dedicated wrapper can still be invoked with call(). Transport concerns are
 * delegated to HttpClientTrait, so the same client works with the real cURL
 * transport and with the fake transport used in tests.
 */
class ApiClient
{
    use AdminMethods;
    use BusinessMethods;
    use ChatMethods;
    use CustomCommandMethods;
    use EphemeralMessageMethods;
    use ForumTopicMethods;
    use GameMethods;
    use GiftMethods;
    use InlineMethods;
    use LocationMethods;
    use ManagedBotMethods;
    use MediaMethods;
    use MessageMethods;
    use PaymentMethods;
    use ReactionMethods;
    use StickerMethods;
    use StoryMethods;
    use UpdateMethods;
    use UserMethods;
    use VerificationMethods;
    use WidgetMethods;
    use HttpClientTrait;

    /** Library version reported in the User-Agent header. */
    public const string VERSION = '3.0.0';

    /** Bot API version whose methods are all implemented. */
    public const string BOT_API_VERSION = '10.4';

    /** Validated configuration shared with every method trait. */
    protected readonly Config $config;

    /**
     * @param Config $config Validated bot configuration
     * @param TransportInterface|null $transport Transport to use; the cURL transport when omitted
     */
    public function __construct(Config $config, ?TransportInterface $transport = null)
    {
        $this->config = $config;

        if ($transport !== null) {
            $this->setTransport($transport);
        }
    }

    /** Get bot configuration. */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Call any API method directly.
     *
     * Supports methods not yet implemented as specific functions, which keeps
     * the client usable as soon as Telegram ships a new Bot API method.
     *
     * @param string $method Bot API method name
     * @param array<string, mixed> $parameters Parameters sent as form fields
     *
     * @see https://core.telegram.org/bots/api
     */
    public function call(string $method, array $parameters = []): mixed
    {
        return $this->apiCall($method, $parameters);
    }

    /**
     * Get Telegram API base URL (contains the token: keep it private).
     *
     * The token must not be logged or exposed in error messages.
     */
    public function getApiUrl(): string
    {
        return $this->config->apiBaseUrl . '/bot' . $this->config->token;
    }

    /** The configured bot token. */
    public function getToken(): string
    {
        return $this->config->getToken();
    }

    /** Check if configuration is valid. */
    public function isValid(): bool
    {
        return Config::isValidToken($this->config->token);
    }
}
