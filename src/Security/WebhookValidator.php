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

    /** Ed25519 key Telegram signs Mini App data with (production) */
    public const string WEB_APP_PUBLIC_KEY = 'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d';

    /** Ed25519 key Telegram signs Mini App data with (test environment) */
    public const string WEB_APP_TEST_PUBLIC_KEY = '40055058a4ee38156a06562e52eece92a771bcd8346a8c4615cb7376eddf72ec';

    /**
     * Check the X-Telegram-Bot-Api-Secret-Token header in constant time
     *
     * Missing or empty secrets fail closed.
     */
    public static function validate(string $secretToken, ?string $header): bool
    {
        return $secretToken !== '' && $header !== null && hash_equals($secretToken, $header);
    }

    /**
     * Validate an HMAC-SHA256 signature of the body
     */
    public static function validateSignature(string $body, string $signature, string $secret): bool
    {
        if ($secret === '' || preg_match('/^[a-fA-F0-9]{64}\z/', $signature) !== 1) {
            return false;
        }
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

            if ($subnetLong === false || preg_match('/^(?:[0-9]|[12][0-9]|3[0-2])\z/', $bits) !== 1) {
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

        if ($fields === null || $botToken === '' || preg_match('/^[a-fA-F0-9]{64}\z/', $hash) !== 1) {
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

        if ($fields === null || $signature === null || $botId <= 0 || preg_match('/^[a-fA-F0-9]{64}\z/', $publicKey) !== 1) {
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
        // parse_str normalizes names, overwrites duplicates and truncates at max_input_vars.
        if ($initData === '' || strlen($initData) > 65536) {
            return null;
        }
        $fields = [];
        foreach (explode('&', $initData) as $pair) {
            if (!str_contains($pair, '=') || preg_match('/%(?![a-fA-F0-9]{2})/', $pair) === 1) {
                return null;
            }
            [$name, $value] = explode('=', $pair, 2);
            $name = urldecode($name);
            $value = urldecode($value);
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) !== 1
                || array_key_exists($name, $fields) || preg_match('/[\x00\r\n]/', $value) === 1) {
                return null;
            }
            $fields[$name] = $value;
        }
        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function isFresh(array $fields, int $maxAge): bool
    {
        $date = $fields['auth_date'] ?? '';
        if ($maxAge < 0 || preg_match('/^[1-9][0-9]*\z/', $date) !== 1) {
            return false;
        }
        $timestamp = filter_var($date, FILTER_VALIDATE_INT);
        $now = time();
        return is_int($timestamp) && $timestamp <= $now
            && ($maxAge === 0 || $now - $timestamp <= $maxAge);
    }

    /**
     * @return non-empty-string|null
     */
    private static function decodeSignature(string $signature): ?string
    {
        try {
            if (preg_match('/^[A-Za-z0-9_-]{86}(?:==)?\z/', $signature) !== 1) {
                return null;
            }
            $binary = sodium_base642bin(rtrim($signature, '='), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
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
