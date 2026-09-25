<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Routing\Command;
use TGbotPHP\Framework\Routing\Pattern;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\InlineKeyboard;
use TGbotPHP\Utilities\Keyboard;
use TGbotPHP\Utilities\MessageParser;

final class UtilitiesTest extends TestCase
{
    public function testStaticKeyboards(): void
    {
        self::assertSame(
            ['inline_keyboard' => [[['text' => 'A', 'callback_data' => 'a'], ['text' => 'B', 'callback_data' => 'b']]]],
            Keyboard::inline(['A' => 'a', 'B' => 'b']),
        );

        self::assertSame(
            ['inline_keyboard' => [
                [['text' => 'A', 'callback_data' => 'a'], ['text' => 'B', 'callback_data' => 'b']],
                [['text' => 'C', 'callback_data' => 'c']],
            ]],
            Keyboard::grid(['A' => 'a', 'B' => 'b', 'C' => 'c'], 2),
        );

        self::assertSame(
            ['inline_keyboard' => [[['text' => 'Site', 'url' => 'https://example.com']]]],
            Keyboard::links(['Site' => 'https://example.com']),
        );
    }

    public function testReplyKeyboards(): void
    {
        self::assertSame(
            ['keyboard' => [[['text' => 'Yes'], ['text' => 'No']], [['text' => 'Location', 'request_location' => true]]], 'resize_keyboard' => true],
            Keyboard::reply([['Yes', 'No'], [['text' => 'Location', 'request_location' => true]]]),
        );
        self::assertSame(['remove_keyboard' => true], Keyboard::remove());
        self::assertSame(['force_reply' => true, 'input_field_placeholder' => 'Name'], Keyboard::forceReply('Name'));
    }

    public function testPaginationKeyboard(): void
    {
        $row = Keyboard::pagination(2, 3)['inline_keyboard'][0];

        self::assertSame(['page:1', 'page:2', 'page:3'], array_column($row, 'callback_data'));
        self::assertCount(2, Keyboard::pagination(1, 3)['inline_keyboard'][0]);
    }

    public function testCallbackDataLengthIsValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Keyboard::button('x', str_repeat('a', 65));
    }

    public function testInlineKeyboardBuilder(): void
    {
        $keyboard = InlineKeyboard::make()
            ->button('Yes', 'yes')
            ->button('No', 'no')
            ->row()
            ->url('Docs', 'https://core.telegram.org')
            ->webApp('App', 'https://example.com/app');

        self::assertSame(
            '{"inline_keyboard":[[{"text":"Yes","callback_data":"yes"},{"text":"No","callback_data":"no"}],'
            . '[{"text":"Docs","url":"https://core.telegram.org"},{"text":"App","web_app":{"url":"https://example.com/app"}}]]}',
            json_encode($keyboard, JSON_UNESCAPED_SLASHES),
        );
    }

    public function testFormatterEscapesUserInput(): void
    {
        self::assertSame('<b>&lt;script&gt; &amp; co</b>', Formatter::bold('<script> & co'));
        // Telegram rejects &apos;: only &lt; &gt; &amp; &quot; and numeric entities are supported
        self::assertSame('it&#039;s &quot;ok&quot;', Formatter::escape('it\'s "ok"'));
        self::assertSame('<a href="https://x.y/?a=1&amp;b=2">x</a>', Formatter::link('x', 'https://x.y/?a=1&b=2'));
        self::assertSame('<a href="tg://user?id=5">Ada</a>', Formatter::mention(5, 'Ada'));
        self::assertSame('<pre><code class="language-php">echo 1;</code></pre>', Formatter::pre('echo 1;', 'php'));
        self::assertSame('Hello, world\\! \\(x\\) \\_a\\_ \\\\', Formatter::escapeMarkdownV2('Hello, world! (x) _a_ \\'));
        self::assertSame('a\\.b\\-c\\!', Formatter::escapeMarkdownV2('a.b-c!'));
    }

    public function testMessageParser(): void
    {
        self::assertSame(['command' => 'start', 'args' => 'payload', 'username' => 'my_bot'], MessageParser::parseCommand('/start@my_bot payload'));
        self::assertNull(MessageParser::parseCommand('hello'));
        self::assertSame(['add', 'buy milk', 'x'], MessageParser::parseArguments('add "buy milk" x'));
        self::assertSame(['alice', 'bob_1'], MessageParser::extractMentions('hi @alice and @bob_1, mail me at a@b.com'));
        self::assertSame(['php', 'bots'], MessageParser::extractHashtags('#php and #bots'));
        self::assertSame(['https://example.com/a'], MessageParser::extractUrls('see https://example.com/a.'));
        self::assertSame(['a@b.com'], MessageParser::extractEmails('mail a@b.com'));
        self::assertSame('bold italic code', MessageParser::stripMarkdown('**bold** _italic_ `code`'));
    }

    public function testRouterPatternMatching(): void
    {
        self::assertSame(['exact'], new Pattern('exact')->match('exact'));
        self::assertNull(new Pattern('exact')->match('other'));
        self::assertSame(['page:12', '12'], new Pattern('page:*')->match('page:12'));
        self::assertSame(["page:1\n2", "1\n2"], new Pattern('page:*')->match("page:1\n2"));
        self::assertSame(['id=7', '7'], new Pattern('/^id=(\d+)$/')->match('id=7'));
        self::assertNull(new Pattern('/^id=(\d+)$/')->match('id=x'));
        self::assertSame(['/[a/'], new Pattern('/[a/')->match('/[a/'), 'Invalid regexes are matched literally');
        self::assertTrue(new Pattern('menu')->isExact());
        self::assertFalse(new Pattern('page:*')->isExact());
    }

    public function testCommandParsing(): void
    {
        $command = Command::parse("/Start@My_Bot  deep link\npayload");
        self::assertNotNull($command);
        self::assertSame('start', $command->name);
        self::assertSame('my_bot', $command->username);
        self::assertSame("deep link\npayload", $command->args);
        self::assertTrue($command->isAddressedTo('my_bot'));
        self::assertTrue($command->isAddressedTo(null));
        self::assertFalse($command->isAddressedTo('other_bot'));

        self::assertNull(Command::parse('/start-now'));
        self::assertNull(Command::parse('/énorme'));
        self::assertSame('start', Command::normalizeName(' /START@my_bot '));
    }

    public function testUpdateParserHelpers(): void
    {
        $message = UpdateParser::fromArray(Updates::message('hi', chatId: 5, userId: 6));
        $callback = UpdateParser::fromArray(Updates::callback('x', chatId: 8, userId: 9));
        $reaction = UpdateParser::fromArray(['update_id' => 1, 'message_reaction' => ['chat' => ['id' => 3], 'user' => ['id' => 4]]]);

        self::assertSame('message', UpdateParser::getType($message));
        self::assertSame(5, UpdateParser::getChat($message)?->id);
        self::assertSame(6, UpdateParser::getUser($message)?->id);
        self::assertSame('callback_query', UpdateParser::getType($callback));
        self::assertSame(8, UpdateParser::getChat($callback)?->id);
        self::assertSame(9, UpdateParser::getUser($callback)?->id);
        self::assertSame(4, UpdateParser::getUser($reaction)?->id);
        self::assertSame('future_update', UpdateParser::getType(UpdateParser::fromArray(['update_id' => 1, 'future_update' => ['a' => 1]])));
    }

    public function testUpdateParserRejectsInvalidPayloads(): void
    {
        $this->expectException(\JsonException::class);

        UpdateParser::parse('[1, 2]');
    }
}
