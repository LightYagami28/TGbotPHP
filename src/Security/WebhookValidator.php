<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

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
     * Validate the X-Telegram-Bot-Api-Secret-Token header
     *
     * Returns true when no secret is configured.
     */
    public static function validate(string $body, string $secretToken, ?string $xTelegramBotApiSecretToken = null): bool
    {
        if ($secretToken === '') {
            return true;
        }

        if ($xTelegramBotApiSecretToken === null || $xTelegramBotApiSecretToken === '') {
            return false;
        }

        return hash_equals($secretToken, $xTelegramBotApiSecretToken);
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
        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        unset($data['hash']);

        /** @var array<string, string> $fields */
        $fields = [];
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                return null;
            }
            $fields[(string) $key] = $value;
        }
        ksort($fields);

        $lines = [];
        foreach ($fields as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', implode("\n", $lines), $secretKey);

        if (!hash_equals($expected, strtolower($hash))) {
            return null;
        }

        if ($maxAge > 0 && (time() - (int) ($fields['auth_date'] ?? 0)) > $maxAge) {
            return null;
        }

        return $fields;
    }
}
