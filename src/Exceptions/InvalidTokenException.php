<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Raised when a Telegram bot token does not have the expected shape.
 *
 * The HTTP 401 code mirrors the response returned by Telegram for an
 * unauthorised request, so callers can reuse it when answering a webhook.
 */
class InvalidTokenException extends TelegramException
{
    /**
     * @param string $message Human readable description of the invalid token
     */
    public function __construct(string $message = 'Invalid Telegram bot token')
    {
        parent::__construct($message, 401);
    }
}
