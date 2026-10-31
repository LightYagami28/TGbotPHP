<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

/**
 * Bot configuration, validated once and immutable
 */
readonly class Config
{
    public const string DEFAULT_API_URL = 'https://api.telegram.org';

    public string $token;
    public string|false $secretToken;

    /**
     * Accept webhook requests that do not present a secret token.
     * The default is false: handle() answers 403 until a secret is configured.
     */
    public bool $allowUnauthenticatedWebhook;

    /** Base URL of the Bot API server; change it to use a local Bot API server */
    public string $apiBaseUrl;

    public bool $enforceHttps;

    /** Request timeout in seconds; long polling adds its own timeout on top */
    public int $timeout;

    public RetryPolicy $retry;

    /** Whether every request and response is logged */
    public bool $debug;

    /** Debug log file, or false for the PHP error log */
    public string|false $debugFile;

    /** Largest webhook body accepted, in bytes. Larger bodies get HTTP 413 */
    public int $maxWebhookBodyBytes;

    /**
     * When true, a handler exception still gets HTTP 200 so Telegram does not
     * redeliver the update forever. Set false to answer 500 and let Telegram retry.
     */
    public bool $acknowledgeHandlerErrors;

    /**
     * Directory a local Bot API server may read files from. Null refuses every
     * absolute file_path, including when apiBaseUrl is not the public API.
     */
    public ?string $localFileRoot;

    /**
     * @param string|false $secretToken Webhook secret token (1-256 characters: A-Z a-z 0-9 _ -). Required for webhooks unless $allowUnauthenticatedWebhook is true
     * @param bool|string $debug true logs to the PHP error log, a string logs to that file
     * @param bool $allowUnauthenticatedWebhook Accept webhook requests with no secret token. Long polling does not use it. The default is false
     * @param int $maxWebhookBodyBytes Maximum accepted webhook body, in bytes
     * @param bool $acknowledgeHandlerErrors Answer 200 when a handler throws. False answers 500 so Telegram retries
     * @param string|null $localFileRoot Existing directory trusted for absolute paths returned by a local Bot API server
     */
    public function __construct(
        string $token,
        string|false $secretToken = false,
        string $apiBaseUrl = self::DEFAULT_API_URL,
        bool $enforceHttps = true,
        int $timeout = 10,
        RetryPolicy $retry = new RetryPolicy(),
        bool|string $debug = false,
        bool $allowUnauthenticatedWebhook = false,
        int $maxWebhookBodyBytes = 1024 * 1024,
        bool $acknowledgeHandlerErrors = true,
        ?string $localFileRoot = null,
    ) {
        if (!self::isValidToken($token)) {
            throw new \InvalidArgumentException('Invalid Telegram bot token');
        }

        $parts = parse_url($apiBaseUrl);

        if ($parts === false || !isset($parts['host']) || $parts['host'] === ''
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7f]/', $apiBaseUrl) === 1) {
            throw new \InvalidArgumentException('API base URL must be an HTTP(S) URL without credentials');
        }

        if ($enforceHttps && $parts['scheme'] !== 'https') {
            throw new \InvalidArgumentException('API base URL must use HTTPS (disable enforceHttps for a local server)');
        }

        if ($maxWebhookBodyBytes < 1) {
            throw new \InvalidArgumentException('Webhook body limit must be a positive number of bytes');
        }

        if ($localFileRoot !== null) {
            $realRoot = realpath($localFileRoot);

            if ($realRoot === false || !is_dir($realRoot)) {
                throw new \InvalidArgumentException('Local file root must be an existing directory');
            }

            $localFileRoot = $realRoot;
        }

        if ($secretToken !== false && preg_match('/^[\w-]{1,256}\z/', $secretToken) !== 1) {
            throw new \InvalidArgumentException('Secret token must be 1-256 characters: A-Z, a-z, 0-9, _ and -');
        }

        $this->token = $token;
        $this->secretToken = $secretToken;
        $this->allowUnauthenticatedWebhook = $allowUnauthenticatedWebhook;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->enforceHttps = $enforceHttps;
        $this->timeout = max(1, $timeout);
        $this->retry = $retry;
        $this->debug = $debug !== false && $debug !== '';
        $this->debugFile = is_string($debug) && $debug !== '' ? $debug : false;
        $this->maxWebhookBodyBytes = $maxWebhookBodyBytes;
        $this->acknowledgeHandlerErrors = $acknowledgeHandlerErrors;
        $this->localFileRoot = $localFileRoot;
    }

    /**
     * Check a token has the "<bot id>:<secret>" shape issued by @BotFather
     */
    public static function isValidToken(string $token): bool
    {
        return strlen($token) >= 10 && preg_match('/^\d+:[\w-]+\z/', $token) === 1;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Numeric bot id: the part of the token before the colon
     */
    public function getBotId(): int
    {
        return (int) strstr($this->token, ':', true);
    }
}
