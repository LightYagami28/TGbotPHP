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
}
