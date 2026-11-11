---
title: "TGbotPHP"
description: "PHP framework for the Telegram Bot API with a typed API client, routing, middleware, conversations, webhooks and long polling."
---

TGbotPHP is a PHP library for the [Telegram Bot API](https://core.telegram.org/bots/api).
It gives you a typed client for every API method and a small framework on top:
routing, conversations, middleware, webhooks and long polling.

```php
use TGbotPHP\Framework\Bot;
use TGbotPHP\Utilities\Keyboard;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

$bot->command('start', function (stdClass $message, Bot $bot): void {
    $bot->reply($message, 'Hello!', [
        'reply_markup' => Keyboard::inline(['Yes' => 'vote:yes', 'No' => 'vote:no']),
    ]);
});

$bot->callback('vote:*', function (stdClass $callback, Bot $bot, array $matches): void {
    $bot->answer($callback, "You voted {$matches[1]}");
});

$bot->poll();
```

## Requirements

- PHP 8.5 or later, with the `curl` and `json` extensions
- An HTTPS endpoint, only if you receive updates through a webhook
- No runtime dependencies

## Installation

```bash
composer require lightyagami28/tgbotphp
```

## What it covers

| Area | What you get |
| --- | --- |
| API client | Every method of Bot API 10.3, `call()` for methods Telegram adds later, and an `$options` array on sending methods for optional parameters |
| Files | `InputFile` uploads local files or in-memory contents, including albums and sticker sets, and `downloadFile()` fetches them back |
| Routing | Commands (including `/cmd@bot` and deep-link payloads), text patterns, callback and inline query patterns (exact, `prefix:*` or a regex), and handlers for any update type |
| Testing | `TGbotPHP\Testing\BotTester` and `FakeUpdate` test your handlers without network access |
| Updates | `handle()` for webhooks with secret token check, `poll()` for long polling with backoff on errors |
| Middleware, events and conversations | Multi-step dialogs with state per user |
| Errors | Typed exceptions, automatic retries after 429 responses and an `onError()` hook |
| Security helpers | Webhook secret token, Telegram IP ranges, Mini App `initData` validation, HTML and MarkdownV2 escaping, per-user rate limiting |

## Configuration

The examples, `docker-compose.yml` and the guides read these environment
variables (names only, see `.env.example`):

| Variable | Needed for | Meaning |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | always | Bot token from @BotFather |
| `TELEGRAM_SECRET_TOKEN` | webhook | Secret Telegram sends in `X-Telegram-Bot-Api-Secret-Token`; without it `handle()` answers 403 |
| `TELEGRAM_BOT_USERNAME` | optional | Username used to match `/command@bot` in groups |
| `OWNER_CHAT_ID` | wiki example only | Chat that receives the example's notifications |

Everything else (API server URL, timeout, retries, debug log, webhook body
limit) is set with the `TGbotPHP\Core\Config` constructor, see
[Configuration](wiki/Configuration.md) and [Advanced usage](docs/advanced.md).

## The command line tool

```bash
export TELEGRAM_BOT_TOKEN=...
vendor/bin/tgbot bot:info
vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
```

## Where to go next

- [Getting started](wiki/Getting-Started.md): the first bot
- [Handling updates](wiki/Handling-Updates.md): commands, patterns, buttons
- [Keyboards and callbacks](wiki/Keyboards-and-Callbacks.md): the buttons
- [Conversations](wiki/Conversations.md): multi-step dialogs
- [Middleware and events](wiki/Middleware-and-Events.md): code around updates
- [API reference](docs/api-reference.md): every method and option
- [Deployment](docs/deployment.md): webhook, systemd, Docker
- [Security guide](wiki/Security-Guide.md): the token, secrets, user input
- [Testing](docs/testing.md): `BotTester` and the end-to-end suite
- [Troubleshooting](wiki/Troubleshooting.md): when the bot does not answer
- [FAQ](wiki/FAQ.md): the questions that come up most often

## Project

- [Security policy](https://github.com/LightYagami28/TGbotPHP/blob/main/SECURITY.md):
  how to report a vulnerability
- [Changelog](wiki/Changelog.md): the releases and the migration notes
- [Contributing](https://github.com/LightYagami28/TGbotPHP/blob/main/CONTRIBUTING.md):
  how to propose a change
- [Support](https://github.com/LightYagami28/TGbotPHP/blob/main/SUPPORT.md):
  where to ask questions
