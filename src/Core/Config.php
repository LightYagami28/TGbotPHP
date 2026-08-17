<?php

declare(strict_types=1);

namespace TGbotPHP\Core;

/**
 * Bot configuration
 */
class Config
{
    public string $token;
    public bool $debug;
    public string|false $debugFile;
    public string|false $secretToken;
    public bool $enforceHttps;

    public function __construct(
        string $token,
        bool $debug = false,
        string|false $debugFile = false,
        string|false $secretToken = false,
        bool $enforceHttps = true
    ) {
        if (empty($token) || strlen($token) < 10) {
            throw new \InvalidArgumentException('Invalid Telegram bot token');
        }

        $this->token = $token;
        $this->debug = $debug;
        $this->debugFile = $debugFile;
        $this->secretToken = $secretToken;
        $this->enforceHttps = $enforceHttps;
    }
}
