<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Raised when a file, cache entry or downloaded payload cannot be written or
 * created, or when an in-memory size limit would be exceeded.
 */
class StorageException extends TelegramException {}
