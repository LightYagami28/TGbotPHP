<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

/**
 * Bot configuration
 *
 * Immutable: every value is validated once in the constructor.
 */
class Config
{
    public readonly string $token;
    public readonly bool $debug;
    public readonly string|false $debugFile;
    public readonly string|false $secretToken;
    public readonly bool $enforceHttps;

    /** Base URL of the Bot API server (change it to use a local Bot API server) */
    public readonly string $apiBaseUrl;

    /** Request timeout in seconds (long polling adds its own timeout on top) */
    public readonly int $timeout;

    /** How many times a request is retried after a 429 flood-control error */
    public readonly int $maxRetries;

    /** Maximum number of seconds to wait before retrying a 429 error */
    public readonly int $maxRetryDelay;

    public function __construct(
        string $token,
        bool $debug = false,
        string|false $debugFile = false,
        string|false $secretToken = false,
        bool $enforceHttps = true,
        string $apiBaseUrl = 'https://api.telegram.org',
        int $timeout = 10,
        int $maxRetries = 1,
        int $maxRetryDelay = 30
    ) {
        if (!self::isValidToken($token)) {
            throw new \InvalidArgumentException('Invalid Telegram bot token');
        }

        if ($enforceHttps && !str_starts_with($apiBaseUrl, 'https://')) {
            throw new \InvalidArgumentException('API base URL must use HTTPS (disable enforceHttps for a local server)');
        }

        if ($secretToken !== false && preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secretToken) !== 1) {
            throw new \InvalidArgumentException('Secret token must be 1-256 characters: A-Z, a-z, 0-9, _ and -');
        }

        $this->token = $token;
        $this->debug = $debug;
        $this->debugFile = $debugFile;
        $this->secretToken = $secretToken;
        $this->enforceHttps = $enforceHttps;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->timeout = max(1, $timeout);
        $this->maxRetries = max(0, $maxRetries);
        $this->maxRetryDelay = max(0, $maxRetryDelay);
    }

    /**
     * Check a token has the "<bot id>:<secret>" shape issued by @BotFather
     */
    public static function isValidToken(string $token): bool
    {
        return strlen($token) >= 10 && preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token) === 1;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Numeric bot id (the part of the token before the colon)
     */
    public function getBotId(): int
    {
        return (int) strstr($this->token, ':', true);
    }
}
