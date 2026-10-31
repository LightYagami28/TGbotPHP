<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Runner;

use JsonException;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Security\WebhookValidator;
use TGbotPHP\Support\Value;
use Throwable;

/**
 * Handles one webhook request
 *
 * Answers 403 when no secret token is configured (unless
 * allowUnauthenticatedWebhook is set) or the presented secret does not match,
 * 405 for a method other than POST, 413 when the body exceeds the configured
 * limit, and 400 for invalid JSON.
 *
 * A handler exception is logged and, by default, still answered with 200:
 * an error status makes Telegram deliver the same update again and again.
 * Set acknowledgeHandlerErrors to false to answer 500 and allow that retry.
 * deduplicateUpdates() remembers update_id so a timeout redelivery is not
 * applied twice. The id is forgotten when the handler fails with a 500, so
 * Telegram's retry is processed.
 */
final readonly class WebhookHandler
{
    public function __construct(private Bot $bot) {}

    /**
     * @param string|null $body Request body (default: php://input, read up to the configured limit)
     * @param string|null $secretToken X-Telegram-Bot-Api-Secret-Token header (default: read from $_SERVER)
     * @param bool $respondFirst Answer Telegram before running the handlers, on PHP-FPM and LiteSpeed:
     *                           Telegram does not wait for slow handlers, but output is not sent
     * @return bool Whether the update was accepted
     */
    public function handle(?string $body = null, ?string $secretToken = null, bool $respondFirst = false): bool
    {
        $config = $this->bot->getConfig();
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        if (is_string($method) && strcasecmp($method, 'POST') !== 0) {
            self::respond(405);

            return false;
        }

        $expected = $config->secretToken;
        $header = $secretToken ?? WebhookValidator::getSecretToken();

        // No secret means refuse, unless the caller opted in. A configured secret
        // is always required, including when that opt-in is also set.
        $authorized = $expected === false
            ? $config->allowUnauthenticatedWebhook
            : WebhookValidator::validate($expected, $header);

        if (!$authorized) {
            self::respond(403);

            return false;
        }

        $limit = $config->maxWebhookBodyBytes;

        if ($limit < 1 || $limit >= PHP_INT_MAX) {
            self::respond(413);

            return false;
        }

        if ($body === null) {
            $read = file_get_contents('php://input', false, null, 0, $limit + 1);
            $body = is_string($read) ? $read : '';
        }

        if (strlen($body) > $limit) {
            self::respond(413);

            return false;
        }

        try {
            $update = UpdateParser::parse($body);
        } catch (JsonException) {
            self::respond(400);

            return false;
        }

        $updateId = Value::nullableInt($update->update_id ?? null);
        $dedup = $this->bot->updateDedupCache();

        if ($dedup !== null && $updateId !== null && !$this->claim($dedup, $updateId)) {
            self::respond(200);

            return true;
        }

        if ($respondFirst) {
            self::respond(200);
            self::finishRequest();
        }

        $this->bot->setHandlingWebhook(true);

        try {
            $this->bot->processUpdate($update);
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());

            if (!$config->acknowledgeHandlerErrors) {
                if ($dedup !== null && $updateId !== null) {
                    $dedup->forget(self::dedupKey($updateId));
                }

                self::respond(500);

                return false;
            }
        } finally {
            $this->bot->setHandlingWebhook(false);
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
        if (!headers_sent()) {
            http_response_code($statusCode);
        }
    }

    /**
     * Remember the update id atomically. A concurrent delivery sees the claim and skips the handlers.
     */
    private function claim(CacheInterface $cache, int $updateId): bool
    {
        $claimed = false;

        $cache->update(self::dedupKey($updateId), static function (mixed $existing) use (&$claimed): array {
            if ($existing !== null) {
                return is_array($existing) ? $existing : ['seen' => true];
            }

            $claimed = true;

            return ['seen' => true];
        }, 86400);

        return $claimed;
    }

    private static function dedupKey(int $updateId): string
    {
        return 'webhook-update:' . $updateId;
    }
}
