<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Exception for Telegram API failures
 */
class ApiException extends TelegramException
{
    private array $apiResponse;

    public function __construct(
        string $message,
        int $code,
        array $apiResponse = []
    ) {
        parent::__construct($message, $code);
        $this->apiResponse = $apiResponse;
    }

    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }
}
