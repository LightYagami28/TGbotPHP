# Configuration

## Config

`new Bot($token)` uses the defaults. Pass a `Config` to change them:

```php
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),   // webhook secret, or false
    apiBaseUrl: Config::DEFAULT_API_URL,             // or a local Bot API server
    enforceHttps: true,
    timeout: 10,                                     // seconds per request
    retry: new RetryPolicy(maxRetries: 1, maxDelay: 30),
    debug: false,                                    // true, or a log file path
));
```

| Option | Default | Meaning |
|---|---|---|
| `token` | required | The token from @BotFather. A malformed token throws `InvalidArgumentException` |
| `secretToken` | `false` | Secret that Telegram sends with every webhook request. 1–256 characters: `A-Z a-z 0-9 _ -` |
| `apiBaseUrl` | `https://api.telegram.org` | Bot API server |
| `enforceHttps` | `true` | Refuse an `apiBaseUrl` without HTTPS |
| `timeout` | `10` | Timeout of each request in seconds. Long polling adds its own timeout on top |
| `retry` | 1 retry, up to 30 s | How requests answered with 429 Too Many Requests are retried |
| `debug` | `false` | Log every request and response |

`Config` is immutable: it is validated once, and nothing can change it afterwards. `$bot->getConfig()` returns it.

## Retries

When Telegram answers 429 Too Many Requests, it says how long to wait (`retry_after`). The `RetryPolicy` decides whether the library waits and sends the request again:

```php
new RetryPolicy(maxRetries: 3, maxDelay: 60); // up to 3 retries, if the wait is at most 60 seconds
RetryPolicy::none();                           // never retry: throw TooManyRequestsException at once
```

In a webhook, a long wait holds the request open: keep `maxDelay` short, or use `RetryPolicy::none()` and handle `TooManyRequestsException` yourself. Network errors are not retried by single requests; `poll()` retries them with an increasing delay.

## Debug log

```php
new Config(token: $token, debug: '/var/log/mybot/telegram.log'); // or true for the PHP error log
```

Every request and response is logged on one line. `secret_token` and `provider_token` are replaced by `<redacted>`, and the bot token never appears, but messages do: keep the log private, and turn it off in production.

## Local Bot API server

A [local Bot API server](https://github.com/tdlib/telegram-bot-api) lifts the file size limits (uploads up to 2 GB) and lets you use your own infrastructure:

```php
$bot = new Bot(new Config(
    token: $token,
    apiBaseUrl: 'http://127.0.0.1:8081',
    enforceHttps: false,
    timeout: 120,
));
```

Before switching, call `$bot->logOut()` once on the official server. With the server in `--local` mode, `downloadFile()` copies files from its disk.

## HTTP transport

Requests go through `Http\TransportInterface`. The default `CurlTransport`:

- verifies TLS certificates and allows only HTTP and HTTPS;
- never follows redirects;
- reuses its connection between requests, which saves a TLS handshake per API call;
- streams downloads to disk.

To use another HTTP client (a proxy, a PSR-18 client, a fake for tests), implement `TransportInterface` (`post()`, `get()`, `download()`) and pass it to the bot:

```php
$bot = new Bot($config, new MyTransport());
```

For tests, use the ready-made fake: see [Testing](Testing).

## BotBuilder

`BotBuilder` configures a bot in one fluent expression, which some prefer for small bots:

```php
use TGbotPHP\Cache\FileCache;
use TGbotPHP\Utilities\BotBuilder;

$bot = new BotBuilder(getenv('TELEGRAM_BOT_TOKEN'))
    ->withUsername('my_bot')
    ->withConversations(new FileCache('/var/lib/mybot/cache'))
    ->addCommand('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello!'))
    ->addCallback('menu:*', fn(stdClass $callback, Bot $bot) => $bot->answer($callback))
    ->setFallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Try /start'))
    ->build();

$bot->handle();
```

It has a `with*()` method for every `Config` option (`withSecretToken`, `withDebug`, `withApiServer`, `withTimeout`, `withRetry`, `withTransport`) and an `add*()` method for every kind of handler.

## Command line tool

`vendor/bin/tgbot` manages the bot from the terminal. It reads the token from `TELEGRAM_BOT_TOKEN` (or `--token=`, which ends up in the shell history).

```bash
export TELEGRAM_BOT_TOKEN=123456789:AA...

vendor/bin/tgbot bot:info                          # id, username, capabilities
vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
vendor/bin/tgbot webhook:set --url=... --drop-pending   # also discard pending updates
vendor/bin/tgbot webhook:info                      # URL, pending updates, last delivery error
vendor/bin/tgbot webhook:delete                    # needed before long polling
vendor/bin/tgbot commands:list --scope=all_private_chats --lang=it
vendor/bin/tgbot commands:delete
vendor/bin/tgbot version
```

Errors go to stderr, and the exit code is 1 when a command fails, so the tool fits in deployment scripts.
