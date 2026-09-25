<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Exception for transport level failures (DNS, TLS, timeouts, ...)
 */
class NetworkException extends TelegramException {}
