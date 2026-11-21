<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception shared by every error raised by the library.
 *
 * Catching this type intercepts API failures, transport failures, plugin and
 * storage errors with a single clause, while the subclasses remain available
 * for more precise handling.
 */
class TelegramException extends Exception
{
    /**
     * @param string $message Human readable description of the failure
     * @param int $code Telegram API status code or an HTTP status when available
     * @param Throwable|null $previous Original error that caused this exception, if any
     */
    public function __construct(
        string $message = 'Telegram API Error',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
