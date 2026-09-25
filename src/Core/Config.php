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

    /**
     * @param string|false $secretToken Webhook secret token (1-256 characters: A-Z a-z 0-9 _ -)
     * @param bool|string $debug true logs to the PHP error log, a string logs to that file
     */
    public function __construct(
        string $token,
        string|false $secretToken = false,
        string $apiBaseUrl = self::DEFAULT_API_URL,
        bool $enforceHttps = true,
        int $timeout = 10,
        RetryPolicy $retry = new RetryPolicy(),
        bool|string $debug = false,
    ) {
        if (!self::isValidToken($token)) {
            throw new \InvalidArgumentException('Invalid Telegram bot token');
        }

        if ($enforceHttps && !str_starts_with($apiBaseUrl, 'https://')) {
            throw new \InvalidArgumentException('API base URL must use HTTPS (disable enforceHttps for a local server)');
        }

        if ($secretToken !== false && preg_match('/^[\w-]{1,256}\z/', $secretToken) !== 1) {
            throw new \InvalidArgumentException('Secret token must be 1-256 characters: A-Z, a-z, 0-9, _ and -');
        }

        $this->token = $token;
        $this->secretToken = $secretToken;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->enforceHttps = $enforceHttps;
        $this->timeout = max(1, $timeout);
        $this->retry = $retry;
        $this->debug = $debug !== false && $debug !== '';
        $this->debugFile = is_string($debug) && $debug !== '' ? $debug : false;
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
