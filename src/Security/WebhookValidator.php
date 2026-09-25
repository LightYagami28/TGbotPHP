<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

use TGbotPHP\Support\Value;

/**
 * Webhook request validation helpers
 */
class WebhookValidator
{
    /**
     * IP ranges Telegram sends webhooks from
     *
     * @see https://core.telegram.org/bots/webhooks#the-short-version
     */
    public const array TELEGRAM_IP_RANGES = [
        '149.154.160.0/20',
        '91.108.4.0/22',
    ];

    /**
     * Check the X-Telegram-Bot-Api-Secret-Token header in constant time
     *
     * An empty $secretToken means no secret is configured: every request passes.
     */
    public static function validate(string $secretToken, ?string $header): bool
    {
        return $secretToken === '' || ($header !== null && $header !== '' && hash_equals($secretToken, $header));
    }

    /**
     * Validate an HMAC-SHA256 signature of the body
     */
    public static function validateSignature(string $body, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $body, $secret);
        return hash_equals($expectedSignature, strtolower($signature));
    }

    /**
     * Read the secret token header of the current request
     */
    public static function getSecretToken(): ?string
    {
        $value = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Check whether an IPv4 address belongs to Telegram's webhook ranges
     *
     * Only use REMOTE_ADDR (not X-Forwarded-For) unless your proxy is trusted.
     *
     * @param string[] $ranges
     */
    public static function isTelegramIp(string $ip, array $ranges = self::TELEGRAM_IP_RANGES): bool
    {
        $address = ip2long($ip);

        if ($address === false) {
            return false;
        }

        foreach ($ranges as $range) {
            [$subnet, $bits] = explode('/', $range) + [1 => '32'];
            $subnetLong = ip2long($subnet);

            if ($subnetLong === false) {
                continue;
            }

            $mask = -1 << (32 - (int) $bits);

            if (($address & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate Telegram Web App (Mini App) init data
     *
     * @param int $maxAge Maximum accepted age of auth_date in seconds (0 disables the check)
     * @return array<string, string>|null The parsed data when valid
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    public static function validateWebAppData(string $initData, string $botToken, int $maxAge = 86400): ?array
    {
        $parsed = self::parseInitData($initData);

        if ($parsed === null) {
            return null;
        }

        [$fields, $hash] = $parsed;
        $valid = hash_equals(self::initDataHash($fields, $botToken), strtolower($hash))
            && ($maxAge === 0 || time() - (int) ($fields['auth_date'] ?? 0) <= $maxAge);

        return $valid ? $fields : null;
    }

    /**
     * @return array{array<string, string>, string}|null The fields without the hash, and the hash
     */
    private static function parseInitData(string $initData): ?array
    {
        parse_str($initData, $data);
        $hash = $data['hash'] ?? null;
        unset($data['hash']);

        $fields = array_filter(Value::map($data), is_string(...));

        // Nested values (field[]=...) are not part of the Telegram format
        $wellFormed = is_string($hash) && $hash !== '' && count($fields) === count($data);

        return $wellFormed ? [$fields, $hash] : null;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function initDataHash(array $fields, string $botToken): string
    {
        ksort($fields);

        $lines = [];
        foreach ($fields as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        return hash_hmac('sha256', implode("\n", $lines), $secretKey);
    }
}
