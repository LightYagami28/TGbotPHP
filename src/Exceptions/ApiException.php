<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

use TGbotPHP\Support\Value;

/**
 * Raised when Telegram answers a method call with an error, or when a
 * successful response cannot be decoded into the expected result type.
 *
 * The raw decoded response is kept so callers can inspect Telegram specific
 * fields such as `parameters` without losing information.
 */
class ApiException extends TelegramException
{
    /** @var array<string, mixed> Raw decoded response returned by the API */
    private array $apiResponse;

    /** Name of the method whose call failed */
    private string $apiMethod;

    /**
     * @param string $message Error description returned by Telegram
     * @param int $code Telegram error code or HTTP status
     * @param array<string, mixed> $apiResponse Raw decoded response body
     * @param string $apiMethod Name of the API method that was called
     */
    public function __construct(
        string $message,
        int $code,
        array $apiResponse = [],
        string $apiMethod = '',
    ) {
        parent::__construct($message, $code);
        $this->apiResponse = $apiResponse;
        $this->apiMethod = $apiMethod;
    }

    /**
     * Raw decoded response, including the `ok`, `description` and
     * `parameters` fields when Telegram provided them.
     *
     * @return array<string, mixed>
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }

    /**
     * Name of the API method that failed, or an empty string when unknown.
     */
    public function getApiMethod(): string
    {
        return $this->apiMethod;
    }

    /**
     * Telegram `parameters` object with fields such as `retry_after` and
     * `migrate_to_chat_id`, normalised to an empty array when absent.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return Value::map($this->apiResponse['parameters'] ?? null);
    }

    /**
     * Whether an edit failed only because the new content is identical to the
     * current one, which happens when a user presses a button twice.
     */
    public function isMessageNotModified(): bool
    {
        return $this->getCode() === 400 && str_contains($this->getMessage(), 'message is not modified');
    }

    /**
     * Supergroup identifier a group was migrated to, when provided.
     */
    public function getMigrateToChatId(): ?int
    {
        return Value::nullableInt($this->getParameters()['migrate_to_chat_id'] ?? null);
    }
}
