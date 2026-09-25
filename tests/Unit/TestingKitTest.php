<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;
use TGbotPHP\Testing\BotTester;
use TGbotPHP\Testing\FakeUpdate;

final class TestingKitTest extends TestCase
{
    public function testRecordsWhatHandlersSend(): void
    {
        $tester = new BotTester();
        $tester->bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello'));
        $tester->bot->callback('page:*', function (stdClass $query, Bot $bot, array $matches): void {
            $bot->answer($query, 'Page ' . Value::string($matches[1] ?? null));
        });

        $tester
            ->receive(FakeUpdate::message('/start@test_bot', chatId: -100123))
            ->receive(FakeUpdate::callbackQuery('page:2'));

        self::assertSame(['sendMessage', 'answerCallbackQuery'], $tester->methods());
        self::assertSame('Hello', $tester->lastSent('sendMessage')['text'] ?? null);
        self::assertSame('-100123', $tester->lastSent('sendMessage')['chat_id'] ?? null);
        self::assertSame('Page 2', $tester->lastSent()['text'] ?? null);
        self::assertCount(1, $tester->sent('answerCallbackQuery'));
        self::assertNull($tester->lastSent('sendPhoto'));

        $tester->reset();
        self::assertSame([], $tester->sent());
    }

    public function testCommandsForOtherBotsAreIgnored(): void
    {
        $tester = new BotTester(username: 'my_bot');
        $tester->bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello'));

        $tester->receive(FakeUpdate::message('/start@other_bot'));

        self::assertSame([], $tester->methods());
    }

    public function testQueuedErrorsReachTheHandlers(): void
    {
        $tester = new BotTester();
        $errors = [];
        $tester->bot->onError(function (\Throwable $e) use (&$errors): void {
            $errors[] = $e->getMessage();
        });
        $tester->bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, 'echo'));

        $tester->transport->queueError(403, 'Forbidden: bot was blocked by the user');
        $tester->receive(FakeUpdate::message('hi'));

        self::assertSame(['Forbidden: bot was blocked by the user'], $errors);
    }

    public function testFakeUpdates(): void
    {
        $message = FakeUpdate::message('hi', chatId: 5, userId: 6, extra: ['message_thread_id' => 3]);
        $group = FakeUpdate::message('hi', chatId: -100);
        $inline = FakeUpdate::inlineQuery('cats', userId: 9);
        $other = FakeUpdate::of('poll', ['id' => 'p']);

        self::assertSame('private', Value::path($message, 'message', 'chat', 'type'));
        self::assertSame(6, Value::path($message, 'message', 'from', 'id'));
        self::assertSame(3, Value::path($message, 'message', 'message_thread_id'));
        self::assertSame('supergroup', Value::path($group, 'message', 'chat', 'type'));
        self::assertSame('cats', Value::path($inline, 'inline_query', 'query'));
        self::assertSame(['id' => 'p'], $other['poll']);

        $ids = array_map(static fn(array $update): int => Value::int($update['update_id']), [$message, $group, $inline, $other]);
        self::assertSame($ids, array_unique($ids), 'Update ids are unique');
    }
}
