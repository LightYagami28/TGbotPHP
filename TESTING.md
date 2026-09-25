# Testing Guide

## Running the tests

```bash
composer install
composer test          # PHPUnit
composer phpstan       # static analysis (level 10 + strict rules)
composer check         # both
```

CI runs the test suite on PHP 8.2, 8.3, 8.4 and 8.5, and PHPStan on every push and pull request.

## Test layout

| File | Covers |
|---|---|
| `tests/Unit/ApiClientTest.php` | Request encoding, uploads, errors, 429 retries, method wrappers |
| `tests/Unit/BotTest.php` | Routing, middleware, events, conversations, webhook handling, long polling, plugins, builder |
| `tests/Unit/CacheTest.php` | `ArrayCache`, `FileCache`, rate limiter, conversations, sessions |
| `tests/Unit/SecurityTest.php` | Secret token, signatures, Telegram IPs, Mini App data |
| `tests/Unit/UtilitiesTest.php` | Keyboards, formatter, parsers, pattern matching |
| `tests/Unit/ValueTest.php` | Type-safe readers for untyped data |
| `tests/Unit/ConsoleTest.php` | `tgbot` CLI |

No test talks to Telegram: `tests/Support/FakeTransport.php` records requests and replays queued responses.

## Testing your own bot

The same fake transport works for your handlers:

```php
use PHPUnit\Framework\TestCase;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Tests\Support\FakeTransport;

final class StartCommandTest extends TestCase
{
    public function testStartGreetsTheUser(): void
    {
        $transport = new FakeTransport();
        $bot = new Bot('123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw', transport: $transport);
        registerHandlers($bot); // your code

        $bot->handleUpdate([
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => 42, 'type' => 'private'],
                'from' => ['id' => 42, 'is_bot' => false, 'first_name' => 'Ada'],
                'text' => '/start',
            ],
        ]);

        $request = $transport->lastRequest();
        self::assertSame('sendMessage', $request['method']);
        self::assertStringContainsString('Ada', $request['fields']['text']);
    }
}
```

Useful `FakeTransport` helpers:

- `queueResult($result)` queues a successful response
- `queueError(400, 'Bad Request: ...', $parameters)` queues an API error
- `requests`, `lastRequest()` and `methods()` show what was sent

## Manual testing with a real bot

```bash
export TELEGRAM_BOT_TOKEN=your_token_here
vendor/bin/tgbot bot:info
vendor/bin/tgbot webhook:delete
php examples/polling.php
```
