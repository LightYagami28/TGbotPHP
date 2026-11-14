<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for Telegram Bot API errors
 */
class TelegramException extends Exception
{
    public function __construct(
        string $message = 'Telegram API Error',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
