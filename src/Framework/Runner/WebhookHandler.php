<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Runner;

use JsonException;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Security\WebhookValidator;
use Throwable;

/**
 * Handles one webhook request
 *
 * Answers 403 for a wrong secret token and 400 for invalid JSON. Handler
 * errors still get a 2xx response: an error status would make Telegram
 * deliver the same update again and again.
 */
final readonly class WebhookHandler
{
    public function __construct(private Bot $bot) {}

    /**
     * @param string|null $body Request body (default: php://input)
     * @param string|null $secretToken X-Telegram-Bot-Api-Secret-Token header (default: read from $_SERVER)
     * @return bool Whether the update was accepted
     */
    public function handle(?string $body = null, ?string $secretToken = null): bool
    {
        $expected = $this->bot->getConfig()->secretToken;

        if ($expected !== false && !WebhookValidator::validate($expected, $secretToken ?? WebhookValidator::getSecretToken())) {
            self::respond(403);
            return false;
        }

        try {
            $update = UpdateParser::parse($body ?? (string) file_get_contents('php://input'));
        } catch (JsonException) {
            self::respond(400);
            return false;
        }

        try {
            $this->bot->processUpdate($update);
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());
        }

        return true;
    }

    private static function respond(int $statusCode): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($statusCode);
        }
    }
}
