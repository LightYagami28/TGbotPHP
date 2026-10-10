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
     * @param bool $respondFirst Answer Telegram before running the handlers, on PHP-FPM and LiteSpeed:
     *                           Telegram does not wait for slow handlers, but output is not sent
     * @return bool Whether the update was accepted
     */
    public function handle(?string $body = null, ?string $secretToken = null, bool $respondFirst = false): bool
    {
        $config = $this->bot->getConfig();
        $expected = $config->secretToken;

        if (($expected === false && !$config->allowUnauthenticatedWebhook)
            || ($expected !== false && !WebhookValidator::validate($expected, $secretToken ?? WebhookValidator::getSecretToken()))) {
            self::respond(403);
            return false;
        }

        $body ??= (string) file_get_contents('php://input', false, null, 0, max(0, $config->maxWebhookBodyBytes + 1));
        if (strlen($body) > $config->maxWebhookBodyBytes) {
            self::respond(413);
            return false;
        }

        try {
            $update = UpdateParser::parse($body);
        } catch (JsonException) {
            self::respond(400);
            return false;
        }

        if ($respondFirst) {
            self::respond(200);
            self::finishRequest();
        }

        try {
            $this->bot->processUpdate($update);
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());
        }

        if (!$respondFirst) {
            self::respond(200);
        }
        return true;
    }

    /**
     * Send the response now and keep running
     */
    private static function finishRequest(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }

    private static function respond(int $statusCode): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($statusCode);
        }
    }
}
