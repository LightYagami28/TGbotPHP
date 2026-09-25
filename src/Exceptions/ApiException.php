<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use TGbotPHP\Support\Value;

/**
 * Exception for Telegram API failures
 */
class ApiException extends TelegramException
{
    /** @var array<string, mixed> */
    private array $apiResponse;

    private string $apiMethod;

    /**
     * @param array<string, mixed> $apiResponse
     */
    public function __construct(
        string $message,
        int $code,
        array $apiResponse = [],
        string $apiMethod = ''
    ) {
        parent::__construct($message, $code);
        $this->apiResponse = $apiResponse;
        $this->apiMethod = $apiMethod;
    }

    /**
     * Raw decoded API response
     *
     * @return array<string, mixed>
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }

    /**
     * Name of the API method that failed
     */
    public function getApiMethod(): string
    {
        return $this->apiMethod;
    }

    /**
     * ResponseParameters object (retry_after, migrate_to_chat_id)
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return Value::map($this->apiResponse['parameters'] ?? null);
    }

    /**
     * Whether an edit failed only because the new content equals the current one
     *
     * Typical when a user presses the button of the page already shown.
     */
    public function isMessageNotModified(): bool
    {
        return $this->getCode() === 400 && str_contains($this->getMessage(), 'message is not modified');
    }

    /**
     * Supergroup id to use when a group has been migrated
     */
    public function getMigrateToChatId(): ?int
    {
        return Value::nullableInt($this->getParameters()['migrate_to_chat_id'] ?? null);
    }
}
