<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Session\ConversationManager;
use TGbotPHP\Support\Payload;
use TGbotPHP\Tests\Support\Updates;

final class SupportTest extends TestCase
{
    public function testRetryPolicy(): void
    {
        $policy = new RetryPolicy(maxRetries: 2, maxDelay: 10);

        self::assertTrue($policy->allows(0, 5));
        self::assertTrue($policy->allows(1, 10));
        self::assertFalse($policy->allows(2, 5), 'No more retries left');
        self::assertFalse($policy->allows(0, 11), 'Waiting too long');
        self::assertFalse(RetryPolicy::none()->allows(0, 0));
    }

    public function testNegativeRetrySettingsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $policy = new RetryPolicy(maxRetries: -1);
        self::fail('Accepted negative retries: ' . $policy->maxRetries);
    }

    public function testPayloadOfMessage(): void
    {
        $update = UpdateParser::fromArray(Updates::message('hi', chatId: 5, userId: 6, extra: [
            'is_topic_message' => true,
            'message_thread_id' => 9,
        ]));
        $message = Payload::message($update);

        self::assertNotNull($message);
        self::assertSame(5, Payload::chatId($message));
        self::assertSame(6, Payload::userId($message));
        self::assertSame(9, Payload::topicId($message));
        self::assertSame($message, Payload::sourceMessage($message));
    }

    public function testPayloadOfCallbackQuery(): void
    {
        $update = UpdateParser::fromArray(Updates::callback('x', chatId: 8, userId: 9));
        $callback = $update->callback_query;
        self::assertInstanceOf(\stdClass::class, $callback);

        self::assertNull(Payload::message($update));
        self::assertSame(8, Payload::chatId($callback));
        self::assertSame(9, Payload::userId($callback));
        self::assertNull(Payload::topicId($callback));
        self::assertSame(5, Payload::sourceMessage($callback)->message_id);
    }

    public function testPayloadWithoutChat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Payload::requireChatId(new \stdClass());
    }

    public function testConversationFromPayload(): void
    {
        $conversations = new ConversationManager(new ArrayCache());
        $message = Payload::message(UpdateParser::fromArray(Updates::message('hi', chatId: 1, userId: 2)));
        self::assertNotNull($message);

        self::assertSame([null, []], $conversations->stateOf($message));

        $conversations->enter($message, 'ask_name', ['step' => 1]);
        $conversations->merge($message, ['name' => 'Ada']);
        self::assertSame(['ask_name', ['step' => 1, 'name' => 'Ada']], $conversations->stateOf($message));
        self::assertSame('ask_name', $conversations->getState(1, 2));

        $conversations->leave($message);
        self::assertSame([null, []], $conversations->stateOf($message));
    }
}
