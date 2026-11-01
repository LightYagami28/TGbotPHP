<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Runner;

use JsonException;
use stdClass;
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
        $received = $this->receive($body, $secretToken);

        if (is_int($received)) {
            self::respond($received);

            return false;
        }

        return $this->dispatch($received, $respondFirst);
    }

    /**
     * Check the request and parse its body
     *
     * @return stdClass|int The update, or the HTTP status to answer with
     */
    private function receive(?string $body, ?string $secretToken): stdClass|int
    {
        $rejected = $this->rejection($secretToken);

        if ($rejected !== 200) {
            return $rejected;
        }

        $limit = $this->bot->getConfig()->maxWebhookBodyBytes;
        $body ??= self::readInput($limit);

        if (strlen($body) > $limit) {
            return 413;
        }

        return self::parseBody($body);
    }

    /**
     * @return int The HTTP status to refuse the request with, or 200 when it may proceed
     */
    private function rejection(?string $secretToken): int
    {
        if (self::isWrongMethod()) {
            return 405;
        }

        if (!$this->isAuthorized($secretToken)) {
            return 403;
        }

        return $this->hasUsableLimit() ? 200 : 413;
    }

    private function hasUsableLimit(): bool
    {
        $limit = $this->bot->getConfig()->maxWebhookBodyBytes;

        return $limit >= 1 && $limit < PHP_INT_MAX;
    }

    private static function isWrongMethod(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        return is_string($method) && strcasecmp($method, 'POST') !== 0;
    }

    private function isAuthorized(?string $secretToken): bool
    {
        $config = $this->bot->getConfig();
        $expected = $config->secretToken;

        // No secret means refuse, unless the caller opted in. A configured secret
        // is always required, including when that opt-in is also set.
        return $expected === false
            ? $config->allowUnauthenticatedWebhook
            : WebhookValidator::validate($expected, $secretToken ?? WebhookValidator::getSecretToken());
    }

    private static function readInput(int $limit): string
    {
        $read = file_get_contents('php://input', false, null, 0, max(0, $limit) + 1);

        return is_string($read) ? $read : '';
    }

    private static function parseBody(string $body): stdClass|int
    {
        try {
            return UpdateParser::parse($body);
        } catch (JsonException) {
            return 400;
        }
    }

    private function dispatch(stdClass $update, bool $respondFirst): bool
    {
        $updateId = self::updateId($update);
        $dedup = $this->bot->updateDedupCache();

        if ($this->isDuplicate($dedup, $updateId)) {
            self::respond(200);

            return true;
        }

        if ($respondFirst) {
            self::respond(200);
            self::finishRequest();
        }

        $accepted = $this->process($update, $dedup, $updateId);

        if (!$accepted || !$respondFirst) {
            self::answer($accepted);
        }

        return $accepted;
    }

    private static function updateId(stdClass $update): ?int
    {
        return Value::nullableInt($update->update_id ?? null);
    }

    private static function answer(bool $accepted): void
    {
        self::respond($accepted ? 200 : 500);
    }

    /**
     * @return bool False when a handler failed and Telegram must retry the update
     */
    private function process(stdClass $update, ?CacheInterface $dedup, ?int $updateId): bool
    {
        $this->bot->setHandlingWebhook(true);

        try {
            $this->bot->processUpdate($update);

            return true;
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());

            if ($this->bot->getConfig()->acknowledgeHandlerErrors) {
                return true;
            }

            $this->release($dedup, $updateId);

            return false;
        } finally {
            $this->bot->setHandlingWebhook(false);
        }
    }

    /**
     * True when the update id was already claimed by an earlier delivery
     */
    private function isDuplicate(?CacheInterface $dedup, ?int $updateId): bool
    {
        return $dedup !== null && $updateId !== null && !$this->claim($dedup, $updateId);
    }

    /**
     * Forget the update id so Telegram's retry is processed
     */
    private function release(?CacheInterface $dedup, ?int $updateId): void
    {
        if ($dedup !== null && $updateId !== null) {
            $dedup->forget(self::dedupKey($updateId));
        }
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
