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

    /** Ed25519 key Telegram signs Mini App data with (production) */
    public const string WEB_APP_PUBLIC_KEY = 'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d';

    /** Ed25519 key Telegram signs Mini App data with (test environment) */
    public const string WEB_APP_TEST_PUBLIC_KEY = '40055058a4ee38156a06562e52eece92a771bcd8346a8c4615cb7376eddf72ec';

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
     * Validate Telegram Web App (Mini App) init data with the bot token
     *
     * @param int $maxAge Maximum accepted age of auth_date in seconds (0 disables the check)
     * @return array<string, string>|null The parsed data (without hash and signature) when valid
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    public static function validateWebAppData(string $initData, string $botToken, int $maxAge = 86400): ?array
    {
        $fields = self::parseInitData($initData);
        $hash = $fields['hash'] ?? '';
        unset($fields['hash']);

        if ($fields === null || $hash === '') {
            return null;
        }

        $valid = hash_equals(self::initDataHash($fields, $botToken), strtolower($hash)) && self::isFresh($fields, $maxAge);
        unset($fields['signature']);

        return $valid ? $fields : null;
    }

    /**
     * Validate Mini App init data without the bot token, with Telegram's Ed25519 signature
     *
     * For services that receive the data of a bot they do not own: they only
     * need the bot id. Requires the sodium extension.
     *
     * @param int $maxAge Maximum accepted age of auth_date in seconds (0 disables the check)
     * @param string $publicKey Telegram's key, hex encoded: WEB_APP_PUBLIC_KEY, or WEB_APP_TEST_PUBLIC_KEY for the test environment
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
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \LogicException('validateWebAppSignature() requires the sodium extension');
        }

        $fields = self::parseInitData($initData);
        $signature = self::decodeSignature($fields['signature'] ?? '');
        unset($fields['hash'], $fields['signature']);

        if ($fields === null || $signature === null || strlen($publicKey) !== 2 * SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $key = hex2bin($publicKey);
        $message = "$botId:WebAppData\n" . self::dataCheckString($fields);

        $valid = is_string($key) && $key !== ''
            && sodium_crypto_sign_verify_detached($signature, $message, $key)
            && self::isFresh($fields, $maxAge);

        return $valid ? $fields : null;
    }

    /**
     * @return array<string, string>|null Every field, or null when the data is malformed
     */
    private static function parseInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        $fields = array_filter(Value::map($data), is_string(...));

        // Nested values (field[]=...) are not part of the Telegram format
        return $fields !== [] && count($fields) === count($data) ? $fields : null;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function isFresh(array $fields, int $maxAge): bool
    {
        return $maxAge === 0 || time() - (int) ($fields['auth_date'] ?? 0) <= $maxAge;
    }

    /**
     * @return non-empty-string|null
     */
    private static function decodeSignature(string $signature): ?string
    {
        try {
            $binary = sodium_base642bin($signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            return null;
        }

        return $binary !== '' && strlen($binary) === SODIUM_CRYPTO_SIGN_BYTES ? $binary : null;
    }

    /**
     * Fields sorted by key, one key=value per line
     *
     * @param array<string, string> $fields
     */
    private static function dataCheckString(array $fields): string
    {
        ksort($fields);

        $lines = [];
        foreach ($fields as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function initDataHash(array $fields, string $botToken): string
    {
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        return hash_hmac('sha256', self::dataCheckString($fields), $secretKey);
    }
}
