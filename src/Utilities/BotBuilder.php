<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Plugin\PluginInterface;

/**
 * Fluent bot factory
 *
 *     $bot = (new BotBuilder(getenv('TELEGRAM_BOT_TOKEN')))
 *         ->withSecretToken(getenv('TELEGRAM_SECRET_TOKEN'))
 *         ->addCommand('start', fn($message, Bot $bot) => $bot->reply($message, 'Welcome!'))
 *         ->build();
 */
class BotBuilder
{
    use CollectsHandlers;

    private string $token;
    private bool|string $debug = false;
    private string|false $secretToken = false;
    private bool $allowUnauthenticatedWebhook = false;
    private int $maxWebhookBodyBytes = 1024 * 1024;
    private bool $acknowledgeHandlerErrors = true;
    private ?string $localFileRoot = null;
    private ?CacheInterface $updateDedup = null;
    private ?string $username = null;
    private string $apiBaseUrl = Config::DEFAULT_API_URL;
    private bool $enforceHttps = true;
    private int $timeout = 10;
    private RetryPolicy $retry;
    private ?TransportInterface $transport = null;
    private ?CacheInterface $conversationCache = null;
    private int $conversationTtl = 3600;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->retry = new RetryPolicy();
    }

    /**
     * Log every request and response: true for the PHP error log, or a file path
     *
     * The log contains message contents: keep it private.
     */
    public function withDebug(bool|string $log = true): self
    {
        $this->debug = $log;
        return $this;
    }

    public function withSecretToken(string $token): self
    {
        $this->secretToken = $token;
        return $this;
    }

    /**
     * Accept webhook requests that do not present a secret token.
     *
     * The default is to refuse them. A secret set with withSecretToken() is
     * still required when this is enabled.
     */
    public function allowUnauthenticatedWebhook(bool $allow = true): self
    {
        $this->allowUnauthenticatedWebhook = $allow;
        return $this;
    }

    /**
     * Reject webhook bodies larger than this many bytes with HTTP 413
     */
    public function withMaxWebhookBody(int $bytes): self
    {
        $this->maxWebhookBodyBytes = $bytes;
        return $this;
    }

    /**
     * Answer 500 when a handler throws, so Telegram redelivers the update.
     * The default answers 200 and logs the error.
     */
    public function acknowledgeHandlerErrors(bool $acknowledge = true): self
    {
        $this->acknowledgeHandlerErrors = $acknowledge;
        return $this;
    }

    /**
     * Directory a local Bot API server may read absolute file paths from
     */
    public function withLocalFileRoot(string $directory): self
    {
        $this->localFileRoot = $directory;
        return $this;
    }

    /**
     * Skip a webhook delivery whose update_id was already accepted
     */
    public function deduplicateUpdates(CacheInterface $cache): self
    {
        $this->updateDedup = $cache;
        return $this;
    }

    public function withUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Use a local Bot API server (https is not required when $enforceHttps is false)
     */
    public function withApiServer(string $baseUrl, bool $enforceHttps = true): self
    {
        $this->apiBaseUrl = $baseUrl;
        $this->enforceHttps = $enforceHttps;
        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * How requests answered with 429 Too Many Requests are retried
     */
    public function withRetry(RetryPolicy $retry): self
    {
        $this->retry = $retry;
        return $this;
    }

    public function withTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function withConversations(CacheInterface $cache, int $ttl = 3600): self
    {
        $this->conversationCache = $cache;
        $this->conversationTtl = $ttl;
        return $this;
    }

    public function build(): Bot
    {
        $config = new Config(
            token: $this->token,
            secretToken: $this->secretToken,
            apiBaseUrl: $this->apiBaseUrl,
            enforceHttps: $this->enforceHttps,
            timeout: $this->timeout,
            retry: $this->retry,
            debug: $this->debug,
        )
            ->withUnauthenticatedWebhook($this->allowUnauthenticatedWebhook)
            ->withWebhookBodyLimit($this->maxWebhookBodyBytes)
            ->withHandlerErrorsAcknowledged($this->acknowledgeHandlerErrors);

        if ($this->localFileRoot !== null) {
            $config = $config->withLocalFileRoot($this->localFileRoot);
        }

        $bot = new Bot($config, $this->transport);

        if ($this->updateDedup !== null) {
            $bot->deduplicateUpdates($this->updateDedup);
        }

        if ($this->username !== null) {
            $bot->setUsername($this->username);
        }

        if ($this->conversationCache !== null) {
            $bot->useConversations($this->conversationCache, $this->conversationTtl);
        }

        $this->registerHandlers($bot);

        return $bot;
    }
}
