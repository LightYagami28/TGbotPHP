<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

/**
 * Webhook and Mini App request validation helpers.
 *
 * Webhook checks live here; Mini App init data validation is delegated to
 * MiniAppValidator, which owns the HMAC and Ed25519 logic. The public entry
 * points and constants stay on this class for backward compatibility.
 */
class WebhookValidator
{
    /** Pattern of a lowercase or uppercase 64 character hex digest. */
    private const string HEX_64 = '/^[a-fA-F0-9]{64}\z/';

    /** Ed25519 key Telegram signs Mini App data with (production). */
    public const string WEB_APP_PUBLIC_KEY = MiniAppValidator::WEB_APP_PUBLIC_KEY;

    /** Ed25519 key Telegram signs Mini App data with (test environment). */
    public const string WEB_APP_TEST_PUBLIC_KEY = MiniAppValidator::WEB_APP_TEST_PUBLIC_KEY;

    /**
     * Check the X-Telegram-Bot-Api-Secret-Token header in constant time.
     *
     * An empty secret fails closed: the caller must refuse the request.
     * Config already rejects an empty secret; this method does too, so a
     * direct call cannot accept every header.
     */
    public static function validate(string $secretToken, ?string $header): bool
    {
        return $secretToken !== '' && $header !== null && hash_equals($secretToken, $header);
    }

    /** Validate an HMAC-SHA256 signature of the request body. */
    public static function validateSignature(string $body, string $signature, string $secret): bool
    {
        if ($secret === '' || preg_match(self::HEX_64, $signature) !== 1) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $body, $secret);

        return hash_equals($expectedSignature, strtolower($signature));
    }

    /** Read the secret token header of the current request, when present. */
    public static function getSecretToken(): ?string
    {
        $value = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Check whether an IPv4 address belongs to Telegram's webhook ranges.
     *
     * Telegram publishes IPv4 ranges only. IPv6 addresses return false.
     * A prefix that is not an integer from 0 to 32 is ignored: casting it
     * would build a mask that matches every address, or throw on the shift.
     * Only use REMOTE_ADDR (not X-Forwarded-For) unless your proxy is trusted.
     *
     * @param string[] $ranges Ranges in CIDR notation, or null for Telegram's published list
     */
    public static function isTelegramIp(string $ip, ?array $ranges = null): bool
    {
        $ranges ??= self::telegramIpRanges();
        $address = ip2long($ip);

        if ($address === false) {
            return false;
        }

        foreach ($ranges as $range) {
            if (self::inRange($address, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IP ranges Telegram sends webhooks from, read from telegram-ip-ranges.json.
     *
     * @see https://core.telegram.org/bots/webhooks#the-short-version
     * @return string[]
     */
    public static function telegramIpRanges(): array
    {
        $json = (string) file_get_contents(__DIR__ . '/telegram-ip-ranges.json');

        return array_values(array_filter((array) json_decode($json, true, 4, JSON_THROW_ON_ERROR), is_string(...)));
    }

    /** Whether an address is inside a CIDR range with a valid prefix. */
    private static function inRange(int $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range, 2) + [1 => '32'];
        $subnetLong = ip2long($subnet);

        if ($subnetLong === false || preg_match('/^(?:\d|[12]\d|3[0-2])\z/', $bits) !== 1) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($address & $mask) === ($subnetLong & $mask);
    }

    /**
     * Validate Telegram Web App (Mini App) init data with the bot token.
     *
     * @param int $maxAge Maximum accepted age of auth_date in seconds (0 disables the check)
     * @return array<string, string>|null The parsed data (without hash and signature) when valid
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    public static function validateWebAppData(string $initData, string $botToken, int $maxAge = 86400): ?array
    {
        return MiniAppValidator::validateWebAppData($initData, $botToken, $maxAge);
    }

    /**
     * Validate Mini App init data without the bot token, with Telegram's Ed25519 signature.
     *
     * @param int $maxAge Maximum accepted age of auth_date in seconds (0 disables the check)
     * @param string $publicKey Telegram's key, hex encoded: WEB_APP_PUBLIC_KEY, or
     *     WEB_APP_TEST_PUBLIC_KEY for the test environment
     * @return array<string, string>|null The parsed data (without hash and signature) when valid
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-for-third-party-use
     */
    public static function validateWebAppSignature(
        string $initData,
        int $botId,
        int $maxAge = 86400,
        string $publicKey = self::WEB_APP_PUBLIC_KEY,
    ): ?array {
        return MiniAppValidator::validateWebAppSignature($initData, $botId, $maxAge, $publicKey);
    }
}
