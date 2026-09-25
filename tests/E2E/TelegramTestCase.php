<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\E2E;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;

/**
 * Base class for end-to-end tests against the real Telegram Bot API
 *
 * Skipped unless TELEGRAM_BOT_TOKEN is set. Tests that send messages also
 * need TELEGRAM_TEST_CHAT_ID: a chat that has started the bot.
 *
 *     TELEGRAM_BOT_TOKEN=... TELEGRAM_TEST_CHAT_ID=... vendor/bin/phpunit --testsuite e2e
 *
 * Use a dedicated test bot: the tests change its commands and description
 * (and restore them afterwards).
 */
abstract class TelegramTestCase extends TestCase
{
    protected Bot $bot;

    #[\Override]
    protected function setUp(): void
    {
        $token = Value::env('TELEGRAM_BOT_TOKEN');

        if ($token === null) {
            self::markTestSkipped('TELEGRAM_BOT_TOKEN is not set');
        }

        $this->bot = new Bot(new Config($token, timeout: 20, maxRetries: 3, maxRetryDelay: 30));
    }

    protected function chatId(): int|string
    {
        $chatId = Value::id(Value::env('TELEGRAM_TEST_CHAT_ID'));

        if ($chatId === null) {
            self::markTestSkipped('TELEGRAM_TEST_CHAT_ID is not set: send /start to the bot and use your user id');
        }

        return $chatId;
    }
}
