<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Raised for transport level failures such as DNS errors, TLS handshake
 * failures, connection refusals and request timeouts.
 *
 * It is a distinct type so transient errors can be retried without mixing
 * them with errors returned by Telegram itself.
 */
class NetworkException extends TelegramException
{
    // No additional state.
}
