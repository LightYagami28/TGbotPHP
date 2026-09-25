<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Security\WebhookValidator;
use TGbotPHP\Tests\Support\Updates;

final class SecurityTest extends TestCase
{
    public function testSecretTokenValidation(): void
    {
        self::assertTrue(WebhookValidator::validate('', null));
        self::assertFalse(WebhookValidator::validate('secret', null));
        self::assertFalse(WebhookValidator::validate('secret', ''));
        self::assertFalse(WebhookValidator::validate('secret', 'wrong'));
        self::assertTrue(WebhookValidator::validate('secret', 'secret'));
    }

    public function testSignatureValidation(): void
    {
        $signature = hash_hmac('sha256', 'body', 'key');

        self::assertTrue(WebhookValidator::validateSignature('body', $signature, 'key'));
        self::assertFalse(WebhookValidator::validateSignature('tampered', $signature, 'key'));
    }

    public function testTelegramIpRanges(): void
    {
        self::assertTrue(WebhookValidator::isTelegramIp('149.154.167.220'));
        self::assertTrue(WebhookValidator::isTelegramIp('91.108.6.1'));
        self::assertFalse(WebhookValidator::isTelegramIp('149.154.176.1'));
        self::assertFalse(WebhookValidator::isTelegramIp('8.8.8.8'));
        self::assertFalse(WebhookValidator::isTelegramIp('not-an-ip'));
    }

    public function testWebAppDataValidation(): void
    {
        $data = [
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => '{"id":279058397,"first_name":"Vladislav"}',
        ];
        ksort($data);
        $checkString = implode("\n", array_map(static fn($k, $v) => "$k=$v", array_keys($data), $data));
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', $checkString, $secret);

        $initData = http_build_query($data + ['hash' => $hash]);

        $validated = WebhookValidator::validateWebAppData($initData, Updates::TOKEN);
        self::assertNotNull($validated);
        self::assertSame($data['user'], $validated['user']);

        self::assertNull(WebhookValidator::validateWebAppData($initData, '1:other'));
        self::assertNull(WebhookValidator::validateWebAppData(str_replace('Vladislav', 'Mallory', $initData), Updates::TOKEN));
        self::assertNull(WebhookValidator::validateWebAppData('auth_date=1', Updates::TOKEN));
    }

    public function testMalformedWebAppDataIsRejected(): void
    {
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', 'auth_date=' . time(), $secret);

        self::assertNotNull(WebhookValidator::validateWebAppData('auth_date=' . time() . "&hash=$hash", Updates::TOKEN));
        self::assertNull(WebhookValidator::validateWebAppData('auth_date=' . time() . "&user[]=x&hash=$hash", Updates::TOKEN), 'Nested values');
        self::assertNull(WebhookValidator::validateWebAppData('auth_date=' . time() . "&0=x&hash=$hash", Updates::TOKEN), 'Numeric keys');
        self::assertNull(WebhookValidator::validateWebAppData('auth_date=' . time() . '&hash=', Updates::TOKEN), 'Empty hash');
    }

    public function testExpiredWebAppDataIsRejected(): void
    {
        $data = ['auth_date' => (string) (time() - 7200), 'query_id' => 'q'];
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', "auth_date={$data['auth_date']}\nquery_id=q", $secret);

        $initData = http_build_query($data + ['hash' => $hash]);

        self::assertNull(WebhookValidator::validateWebAppData($initData, Updates::TOKEN, 3600));
        self::assertNotNull(WebhookValidator::validateWebAppData($initData, Updates::TOKEN, 0));
    }

    public function testWebAppSignatureForThirdParties(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keyPair));
        $fields = ['auth_date' => (string) time(), 'query_id' => 'AAH', 'user' => '{"id":7,"first_name":"Ada"}'];

        $signature = sodium_bin2base64(
            sodium_crypto_sign_detached("12345678:WebAppData\nauth_date={$fields['auth_date']}\nquery_id=AAH\nuser={$fields['user']}", sodium_crypto_sign_secretkey($keyPair)),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
        $initData = http_build_query($fields + ['signature' => $signature, 'hash' => 'ignored']);

        self::assertSame($fields, WebhookValidator::validateWebAppSignature($initData, 12345678, publicKey: $publicKey));

        self::assertNull(WebhookValidator::validateWebAppSignature($initData, 87654321, publicKey: $publicKey), 'Other bot');
        self::assertNull(WebhookValidator::validateWebAppSignature($initData, 12345678), 'Not signed by Telegram');
        self::assertNull(WebhookValidator::validateWebAppSignature(str_replace('Ada', 'Eve', $initData), 12345678, publicKey: $publicKey), 'Tampered');
        self::assertNull(WebhookValidator::validateWebAppSignature(http_build_query($fields), 12345678, publicKey: $publicKey), 'No signature');
        self::assertNull(WebhookValidator::validateWebAppSignature(http_build_query($fields + ['signature' => '!!']), 12345678, publicKey: $publicKey), 'Invalid base64');
        self::assertNull(WebhookValidator::validateWebAppSignature($initData, 12345678, publicKey: 'abcd'), 'Invalid key');
    }

    public function testExpiredWebAppSignatureIsRejected(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $authDate = (string) (time() - 7200);
        $signature = sodium_bin2base64(
            sodium_crypto_sign_detached("1:WebAppData\nauth_date=$authDate", sodium_crypto_sign_secretkey($keyPair)),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
        $initData = "auth_date=$authDate&signature=$signature";
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keyPair));

        self::assertNull(WebhookValidator::validateWebAppSignature($initData, 1, 3600, $publicKey));
        self::assertSame(['auth_date' => $authDate], WebhookValidator::validateWebAppSignature($initData, 1, 0, $publicKey));
    }

    public function testTelegramPublicKeysAreWellFormed(): void
    {
        foreach ([WebhookValidator::WEB_APP_PUBLIC_KEY, WebhookValidator::WEB_APP_TEST_PUBLIC_KEY] as $key) {
            self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen((string) hex2bin($key)));
        }
    }

    public function testWebAppDataInTelegramsOrderAndCase(): void
    {
        // Telegram does not sort the fields: the check string must be sorted by the validator
        $fields = ['user' => '{"id":1}', 'query_id' => 'Q', 'auth_date' => (string) time()];
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', "auth_date={$fields['auth_date']}\nquery_id=Q\nuser={\"id\":1}", $secret);

        self::assertNotNull(WebhookValidator::validateWebAppData(http_build_query($fields + ['hash' => $hash]), Updates::TOKEN));
        self::assertNotNull(WebhookValidator::validateWebAppData(http_build_query($fields + ['hash' => strtoupper($hash)]), Updates::TOKEN));
        self::assertNull(WebhookValidator::validateWebAppData(http_build_query($fields + ['hash' => '']), Updates::TOKEN));
    }

    public function testSignatureValidationIgnoresHexCase(): void
    {
        self::assertTrue(WebhookValidator::validateSignature('body', strtoupper(hash_hmac('sha256', 'body', 'key')), 'key'));
    }

    public function testIpRangesWithoutPrefixMatchOneAddress(): void
    {
        self::assertTrue(WebhookValidator::isTelegramIp('10.0.0.1', ['10.0.0.1']));
        self::assertFalse(WebhookValidator::isTelegramIp('10.0.0.2', ['10.0.0.1']));
        self::assertTrue(WebhookValidator::isTelegramIp('10.0.0.200', ['10.0.0.0/24']));
        self::assertFalse(WebhookValidator::isTelegramIp('10.0.0.1', ['not-a-range/24']));
    }

    public function testWebAppDataAgeLimitIsInclusive(): void
    {
        $authDate = (string) (time() - 60);
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $initData = "auth_date=$authDate&hash=" . hash_hmac('sha256', "auth_date=$authDate", $secret);

        self::assertNotNull(WebhookValidator::validateWebAppData($initData, Updates::TOKEN, maxAge: 60));
        self::assertNull(WebhookValidator::validateWebAppData($initData, Updates::TOKEN, maxAge: 30));
    }
}
