<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\E2E;

use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Support\Value;

/**
 * Bot-level methods: no chat needed
 */
final class BotProfileTest extends TelegramTestCase
{
    public function testGetMe(): void
    {
        $me = $this->bot->getMe();

        self::assertTrue($me['is_bot'] ?? null);
        self::assertSame($this->bot->getConfig()->getBotId(), Value::int($me['id'] ?? null));
        self::assertNotSame('', Value::string($me['username'] ?? null));
    }

    public function testCommandsRoundTrip(): void
    {
        $original = $this->bot->getMyCommands();

        try {
            self::assertTrue($this->bot->setMyCommands(['start' => 'Start the bot', 'e2e' => 'E2E test command']));

            $commands = $this->bot->getMyCommands();
            self::assertSame(['start', 'e2e'], array_column($commands, 'command'));

            self::assertTrue($this->bot->setMyCommands(['private' => 'Only in private chats'], 'all_private_chats'));
            self::assertSame(['private'], array_column($this->bot->getMyCommands('all_private_chats'), 'command'));
            self::assertTrue($this->bot->deleteMyCommands('all_private_chats'));
        } finally {
            $original === [] ? $this->bot->deleteMyCommands() : $this->bot->setMyCommands($original);
        }
    }

    public function testDescriptionRoundTrip(): void
    {
        $original = $this->bot->getMyShortDescription();

        try {
            $text = 'TGbotPHP E2E ' . date('H:i:s');
            self::assertTrue($this->bot->setMyShortDescription($text));
            self::assertSame($text, $this->bot->getMyShortDescription());
        } finally {
            $this->bot->setMyShortDescription($original);
        }
    }

    public function testWebhookRoundTrip(): void
    {
        $secret = bin2hex(random_bytes(16));

        try {
            self::assertTrue($this->bot->setWebhook('https://example.com/tgbotphp-e2e', secretToken: $secret, dropPendingUpdates: false));
            $info = $this->bot->getWebhookInfo();
            self::assertSame('https://example.com/tgbotphp-e2e', $info['url'] ?? null);
        } finally {
            self::assertTrue($this->bot->deleteWebhook());
        }

        self::assertSame('', $this->bot->getWebhookInfo()['url'] ?? null);
    }

    public function testGetUpdatesReturnsList(): void
    {
        // The response shape is validated by apiCallList(): reaching this line means it was a list of Update objects
        $updates = $this->bot->getUpdates(limit: 1, timeout: 0);

        self::assertLessThanOrEqual(1, count($updates));
    }

    public function testApiErrorsAreTyped(): void
    {
        try {
            $this->bot->getChat(1);
            self::fail('getChat(1) should fail');
        } catch (ApiException $e) {
            self::assertSame(400, $e->getCode());
            self::assertSame('getChat', $e->getApiMethod());
            self::assertStringContainsString('chat not found', $e->getMessage());
        }
    }

    public function testUnknownMethodThroughCall(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->bot->call('thisMethodDoesNotExist');
    }
}
