<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

use LogicException;
use SodiumException;

/**
 * Validation of Telegram Mini App (Web App) init data.
 *
 * Mini Apps use HMAC (with the bot token) or Ed25519 (with Telegram's public
 * key), a different trust model from webhook requests. Keeping that logic here
 * leaves WebhookValidator focused on webhook security and both classes below
 * the method-count limit. WebhookValidator keeps delegating entry points.
 */
final class MiniAppValidator
{
    /** Pattern of a lowercase or uppercase 64 character hex digest. */
    private const string HEX_64 = '/^[a-fA-F0-9]{64}\z/';

    /** Ed25519 key Telegram signs Mini App data with (production). */
    public const string WEB_APP_PUBLIC_KEY = 'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d';

    /** Ed25519 key Telegram signs Mini App data with (test environment). */
    public const string WEB_APP_TEST_PUBLIC_KEY = '40055058a4ee38156a06562e52eece92a771bcd8346a8c4615cb7376eddf72ec';

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
        $fields = self::parseInitData($initData);

        if ($fields === null || $botToken === '') {
            return null;
        }

        $hash = $fields['hash'] ?? '';
        unset($fields['hash']);

        return self::hashMatches($fields, $hash, $botToken, $maxAge) ? self::withoutSignature($fields) : null;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function hashMatches(array $fields, string $hash, string $botToken, int $maxAge): bool
    {
        return preg_match(self::HEX_64, $hash) === 1
            && hash_equals(self::initDataHash($fields, $botToken), strtolower($hash))
            && self::isFresh($fields, $maxAge);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private static function withoutSignature(array $fields): array
    {
        unset($fields['signature']);

        return $fields;
    }

    /**
     * Validate Mini App init data without the bot token, with Telegram's Ed25519 signature.
     *
     * For services that receive the data of a bot they do not own: they only
     * need the bot id. Requires the sodium extension.
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
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new LogicException('validateWebAppSignature() requires the sodium extension');
        }

        $fields = $botId > 0 ? self::parseInitData($initData) : null;
        $key = self::decodePublicKey($publicKey);
        $signature = $fields === null ? null : self::decodeSignature($fields['signature'] ?? '');

        if ($fields === null || $key === null || $signature === null) {
            return null;
        }

        unset($fields['hash'], $fields['signature']);

        return self::signatureMatches($fields, "$botId:WebAppData\n", [$signature, $key], $maxAge) ? $fields : null;
    }

    /**
     * @param array<string, string> $fields
     * @param array{non-empty-string, non-empty-string} $signatureAndKey
     */
    private static function signatureMatches(array $fields, string $prefix, array $signatureAndKey, int $maxAge): bool
    {
        [$signature, $key] = $signatureAndKey;

        return self::isFresh($fields, $maxAge)
            && sodium_crypto_sign_verify_detached($signature, $prefix . self::dataCheckString($fields), $key);
    }

    /**
     * @return non-empty-string|null
     */
    private static function decodePublicKey(string $publicKey): ?string
    {
        $key = preg_match(self::HEX_64, $publicKey) === 1 ? hex2bin($publicKey) : false;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Parse init data without parse_str, which rewrites names and keeps only
     * the last duplicate.
     *
     * @return array<string, string>|null Every field, or null when the data is malformed
     */
    private static function parseInitData(string $initData): ?array
    {
        if ($initData === '' || strlen($initData) > 65536) {
            return null;
        }

        $fields = [];

        foreach (explode('&', $initData) as $pair) {
            if (!self::addInitDataField($pair, $fields)) {
                return null;
            }
        }

        return $fields;
    }

    /**
     * Parse one key=value pair, adding it to $fields; false when it is malformed.
     *
     * @param array<string, string> $fields
     */
    private static function addInitDataField(string $pair, array &$fields): bool
    {
        $parts = self::splitPair($pair);

        if ($parts === null || array_key_exists($parts[0], $fields)) {
            return false;
        }

        $fields[$parts[0]] = $parts[1];

        return true;
    }

    /**
     * @return array{string, string}|null Decoded name and value, or null when the pair is malformed
     */
    private static function splitPair(string $pair): ?array
    {
        if (!str_contains($pair, '=') || preg_match('/%(?![a-fA-F0-9]{2})/', $pair) === 1) {
            return null;
        }

        [$name, $value] = explode('=', $pair, 2);
        $name = urldecode($name);
        $value = urldecode($value);

        return self::isValidField($name, $value) ? [$name, $value] : null;
    }

    /** Whether a decoded field name and value can be part of signed data. */
    private static function isValidField(string $name, string $value): bool
    {
        return preg_match('/^[A-Za-z_]\w*\z/', $name) === 1 && preg_match('/[\x00\r\n]/', $value) !== 1;
    }

    /**
     * A disabled age check (maxAge 0) still rejects a missing, non-numeric or future auth_date.
     *
     * @param array<string, string> $fields
     */
    private static function isFresh(array $fields, int $maxAge): bool
    {
        $date = $fields['auth_date'] ?? '';

        if ($maxAge < 0 || preg_match('/^[1-9]\d*\z/', $date) !== 1) {
            return false;
        }

        return self::withinAge((int) filter_var($date, FILTER_VALIDATE_INT), $maxAge);
    }

    private static function withinAge(int $timestamp, int $maxAge): bool
    {
        $now = time();

        return $timestamp > 0 && $timestamp <= $now && ($maxAge === 0 || $now - $timestamp <= $maxAge);
    }

    /**
     * @return non-empty-string|null
     */
    private static function decodeSignature(string $signature): ?string
    {
        $binary = preg_match('/^[A-Za-z0-9_-]+\z/', $signature) === 1 ? self::base64Url($signature) : null;

        return $binary !== null && strlen($binary) === SODIUM_CRYPTO_SIGN_BYTES ? $binary : null;
    }

    /** Decode an unpadded URL-safe base64 signature, or null when malformed. */
    private static function base64Url(string $signature): ?string
    {
        try {
            return sodium_base642bin($signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return null;
        }
    }

    /**
     * Fields sorted by key, one key=value per line.
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
