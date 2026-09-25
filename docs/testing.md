# Testing Guide

## Running the tests

```bash
composer install
composer test          # PHPUnit
composer phpstan       # static analysis (level 10 + strict rules)
composer check         # both
```

CI runs the test suite on PHP 8.4 and 8.5 (plus 8.6, still in development, as a non-blocking job) and PHPStan on every push and pull request. The PHP 8.4 job reports code coverage in its summary.

## Test layout

| File | Covers |
|---|---|
| `tests/Unit/BotApiCoverageTest.php` | Every Bot API method and update type against `tools/bot-api.json`, extracted from the official documentation |
| `tests/Unit/ApiClientTest.php` | Request encoding, uploads, downloads, errors, 429 retries |
| `tests/Unit/BotTest.php` | Routing, middleware, events, conversations, replies, webhook handling, plugins |
| `tests/Unit/LongPollingTest.php` | Long polling: backoff, 429 waits, fatal errors |
| `tests/Unit/CurlTransportTest.php` | The cURL transport, against PHP's built-in web server |
| `tests/Unit/CacheTest.php` | `ArrayCache`, `FileCache` (including concurrent updates from several processes), rate limiter, conversations, sessions |
| `tests/Unit/SecurityTest.php` | Secret token, Telegram IPs, Mini App data and signatures |
| `tests/Unit/UtilitiesTest.php` | Keyboards, formatter, parsers, entities, pattern matching |
| `tests/Unit/ConsoleTest.php` | `tgbot` CLI |

CI requires at least 95% of the lines to be covered.

## Testing your own bot

`TGbotPHP\Testing` ships with the library. `BotTester` gives you a bot whose requests never leave the process, and `FakeUpdate` builds the updates Telegram would send:

```php
use PHPUnit\Framework\TestCase;
use TGbotPHP\Testing\BotTester;
use TGbotPHP\Testing\FakeUpdate;

final class StartCommandTest extends TestCase
{
    public function testStartGreetsTheUser(): void
    {
        $tester = new BotTester();
        registerHandlers($tester->bot); // your code

        $tester->receive(FakeUpdate::message('/start', chatId: 42));

        self::assertSame(['sendMessage'], $tester->methods());
        self::assertStringContainsString('Welcome', $tester->lastSent('sendMessage')['text']);
    }

    public function testBlockedUsersAreHandled(): void
    {
        $tester = new BotTester();
        registerHandlers($tester->bot);

        $tester->transport->queueError(403, 'Forbidden: bot was blocked by the user');
        $tester->receive(FakeUpdate::callbackQuery('subscribe'));

        // ... assert what your error handler did
    }
}
```

- `FakeUpdate::message()`, `callbackQuery()`, `inlineQuery()`, and `of($type, $payload)` for any other update type
- `$tester->sent('sendMessage')`, `lastSent()`, `methods()` and `reset()` show what the bot sent. Parameters are encoded as Telegram receives them: numbers are strings, arrays are JSON.
- `$tester->transport` is a `FakeTransport`: `queueResult($result)`, `queueError($code, $description, $parameters)` and `queueException($e)` choose the next answers. Without them, `send*`, `get*`, `edit*`... receive a message and other methods receive `true`.

`BotTester` does not depend on PHPUnit: use it with any test framework.

## Manual testing with a real bot

```bash
export TELEGRAM_BOT_TOKEN=your_token_here
vendor/bin/tgbot bot:info
vendor/bin/tgbot webhook:delete
php examples/polling.php
```

## End-to-end tests against Telegram

`tests/E2E` calls the real Bot API. The suite is skipped unless a token is set, and it does not run by default:

```bash
# Bot-level methods: commands, description, webhook, errors
TELEGRAM_BOT_TOKEN=... vendor/bin/phpunit --testsuite e2e

# Also messages, uploads, downloads, media groups, polls, reactions...
# TELEGRAM_TEST_CHAT_ID is your user id: send /start to the bot first
TELEGRAM_BOT_TOKEN=... TELEGRAM_TEST_CHAT_ID=... vendor/bin/phpunit --testsuite e2e
```

From GitHub, run the **End-to-end** workflow (Actions tab), after setting `TELEGRAM_BOT_TOKEN` and `TELEGRAM_TEST_CHAT_ID` as secrets of the `telegram-e2e` environment.

Use a dedicated test bot. The tests change its commands and description and restore them afterwards. They delete the messages they send, except dice, which Telegram does not allow deleting in private chats for 24 hours.
