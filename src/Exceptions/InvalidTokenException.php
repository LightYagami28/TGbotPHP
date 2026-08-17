<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Exception for invalid Telegram bot token
 */
class InvalidTokenException extends TelegramException
{
    public function __construct(string $message = "Invalid Telegram bot token")
    {
        parent::__construct($message, 401);
    }
}
