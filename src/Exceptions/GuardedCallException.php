<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use ErrorException;

/**
 * Raised inside GuardedCall when a PHP warning is promoted to an exception.
 *
 * Extends ErrorException to keep the original severity, file and line, while
 * giving the failure a dedicated type instead of a generic exception.
 */
class GuardedCallException extends ErrorException
{
    // No additional state.
}
