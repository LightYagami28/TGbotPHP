<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\E2E;

use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Support\Value;

/**
 * Methods added in recent Bot API versions, called against the real API
 *
 * Only methods without lasting effects: reads, and a draft that disappears.
 */
final class BotApi10Test extends TelegramTestCase
{
    public function testStarBalanceAndGifts(): void
    {
        $balance = $this->bot->getMyStarBalance();
        self::assertIsInt(Value::path($balance, 'amount'));

        $gifts = $this->bot->getAvailableGifts();
        self::assertIsArray(Value::path($gifts, 'gifts'));
    }

    public function testUserProfileAudios(): void
    {
        $chatId = $this->chatId();

        if (!is_int($chatId) || $chatId < 0) {
            self::markTestSkipped('TELEGRAM_TEST_CHAT_ID is not a user');
        }

        $audios = $this->bot->getUserProfileAudios($chatId);
        self::assertIsInt(Value::path($audios, 'total_count'));
    }

    public function testMessageDraftInPrivateChat(): void
    {
        $chatId = $this->chatId();

        if (!is_int($chatId) || $chatId < 0) {
            self::markTestSkipped('Drafts are only shown in private chats');
        }

        try {
            self::assertTrue($this->bot->sendMessageDraft($chatId, random_int(1, PHP_INT_MAX), 'TGbotPHP end-to-end test: this preview disappears'));
        } catch (ApiException $e) {
            // Drafts can be limited to bots with some features enabled: the request itself was accepted as valid
            self::assertSame(400, $e->getCode(), $e->getMessage());
            self::assertStringNotContainsString('method not found', strtolower($e->getMessage()));
        }
    }
}
