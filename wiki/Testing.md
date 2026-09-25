# Testing

`TGbotPHP\Testing` ships with the library. With it you test your handlers the way you test the rest of your code: fast, offline, and without a real bot.

## BotTester

`BotTester` gives you a bot whose requests never leave the process. You feed it updates built by `FakeUpdate`, then check what it sent:

```php
use PHPUnit\Framework\TestCase;
use TGbotPHP\Testing\BotTester;
use TGbotPHP\Testing\FakeUpdate;

final class StartCommandTest extends TestCase
{
    public function testStartGreetsTheUser(): void
    {
        $tester = new BotTester();
        registerHandlers($tester->bot); // the function that sets up your bot

        $tester->receive(FakeUpdate::message('/start', chatId: 42));

        self::assertSame(['sendMessage'], $tester->methods());
        self::assertSame('42', $tester->lastSent('sendMessage')['chat_id']);
        self::assertStringContainsString('Welcome', $tester->lastSent('sendMessage')['text']);
    }
}
```

Structure your bot so that the handlers are registered by a function or a class you can call from the tests, and the entry point only adds `$bot->poll()` or `$bot->handle()`.

| `BotTester` | |
|---|---|
| `new BotTester($config, $username)` | A bot with a valid fake token, wired to a `FakeTransport`. The username defaults to `test_bot` |
| `$tester->bot` | The bot: register your handlers on it |
| `receive($update)` | Process an update, as if Telegram had sent it |
| `sent($method)` | The parameters of every request (to one method) |
| `lastSent($method)` | The parameters of the last request (to one method), or `null` |
| `methods()` | The methods called, in order |
| `reset()` | Forget the requests sent so far |
| `$tester->transport` | The `FakeTransport`, to choose Telegram's answers |

Parameters are encoded as Telegram receives them: numbers and booleans are strings, and arrays such as `reply_markup` are JSON.

```php
$markup = json_decode($tester->lastSent('sendMessage')['reply_markup'], true);
self::assertSame('page:2', $markup['inline_keyboard'][0][1]['callback_data']);
```

## FakeUpdate

| | Builds |
|---|---|
| `FakeUpdate::message($text, $chatId, $userId, $extra)` | A message. Negative chat ids are supergroups. `$extra` adds fields: `['photo' => [...]]`, `['reply_to_message' => [...]]` |
| `FakeUpdate::callbackQuery($data, $chatId, $userId, $messageId)` | A button press on a message of the bot |
| `FakeUpdate::inlineQuery($query, $userId)` | An inline query |
| `FakeUpdate::of($type, $payload)` | Any other update: `FakeUpdate::of('chat_member', [...])` |

Each update gets a new `update_id`.

## Telegram's answers

Without instructions, the fake answers `send*`, `get*`, `edit*`, `copy*`, `forward*`, `stop*`, `create*` and `upload*` methods with a message-like object, and the other methods with `true`. Queue answers to test other cases:

```php
$tester->transport->queueResult(['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'my_bot']);
$tester->transport->queueError(403, 'Forbidden: bot was blocked by the user');
$tester->transport->queueError(429, 'Too Many Requests: retry after 5', ['retry_after' => 5]);
$tester->transport->queueException(new NetworkException('Connection timed out'));
```

Queued answers are used in order, one per request.

## Conversations and time

`BotTester` uses a plain bot: enable conversations as your bot does, with an `ArrayCache` in tests:

```php
use TGbotPHP\Cache\ArrayCache;

$tester->bot->useConversations(new ArrayCache());

$tester->receive(FakeUpdate::message('/order'));
$tester->receive(FakeUpdate::message('Pizza'));
$tester->receive(FakeUpdate::message('2'));

self::assertStringContainsString('Ordered 2', $tester->lastSent('sendMessage')['text']);
```

## Without BotTester

The pieces work on their own: `new Bot($token, new FakeTransport())`, or `$bot->handleUpdate(FakeUpdate::message('/start'))` on any bot. `handleUpdate()` throws handler exceptions when there are no `onError()` listeners, so a failing handler fails the test.

## End-to-end tests

The library's own suite also runs against the real Telegram API (`vendor/bin/phpunit --testsuite e2e`). See [`docs/testing.md`](https://github.com/LightYagami28/TGbotPHP/blob/main/docs/testing.md). For your bot, a test bot created with @BotFather and a private chat with it are enough to try it by hand.
