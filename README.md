# TGbotPHP

Professional Telegram Bot Framework for PHP 8.4+

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Tests](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml)
[![Code Analysis](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml)
[![Version](https://img.shields.io/badge/Version-3.0.0-blue)](CHANGELOG.md)

Fork of [OpenTelegramFiles/TGbotPHP](https://github.com/OpenTelegramFiles/TGbotPHP)

> Production-ready. Tested. Security-first. Zero runtime dependencies.

## Features

- **Complete Bot API client**: 115+ typed methods, plus `call()` for anything else. Sending and editing methods take an `$options` array for optional or newer parameters.
- **Uploads that work**: `InputFile` for local files and in-memory contents, including media groups and sticker sets (`attach://` is handled for you).
- **Routing**: commands (with `/cmd@bot` and deep-link payloads), text patterns, callback and inline query patterns (exact, `wildcard:*` or regex), any update type.
- **Webhooks and long polling**: `$bot->handle()` checks the secret token; `$bot->poll()` retries with backoff.
- **Middleware**: simple (`return false` to stop) or onion style (`$next()`).
- **Conversations**: multi-step dialogs with per-user state.
- **Reliability**: 429 flood-control retries, typed exceptions (`ApiException`, `TooManyRequestsException`, `NetworkException`), errors sent to an `onError` handler.
- **Security**: webhook secret token, Telegram IP ranges, Mini App `initData` validation, HTML/MarkdownV2 escaping, a rate-limiting middleware.
- **Type safety**: PHPStan level 10 (max) with strict rules on the whole codebase, results validated at runtime, and `Support\Value` for reading payloads.
- **Tooling**: persistent `FileCache`, keyboard builders, `tgbot` CLI, PHPUnit test suite (PHP 8.4, 8.5, and 8.6 in development).

## Installation

```bash
composer require lightyagami28/tgbotphp
```

or from source:

```bash
git clone https://github.com/LightYagami28/TGbotPHP.git
cd TGbotPHP
composer install
```

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Framework\Bot;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\Keyboard;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

// Handlers receive the update payload (stdClass), the bot, then route data
$bot->command('start', function (stdClass $message, Bot $bot, string $payload) {
    $bot->reply($message, 'Hello ' . Formatter::bold($message->from->first_name) . '!', [
        'reply_markup' => Keyboard::inline(['👍' => 'vote:up', '👎' => 'vote:down']),
    ]);
});

$bot->callback('vote:*', function (stdClass $callback, Bot $bot, array $matches) {
    $bot->answer($callback, "You voted {$matches[1]}");
});

$bot->hears('/^ping$/i', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'pong'));

$bot->poll();      // long polling, no web server needed
// $bot->handle(); // or: webhook entry point
```

More in [`examples/`](examples): a [long polling bot](examples/polling.php) and a [webhook bot](examples/webhook.php) with conversations and rate limiting.

## Webhooks

```php
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),
));
$bot->setUsername('my_bot'); // ignore /commands@other_bot in groups

// ... handlers ...

$bot->handle(); // 403 on a wrong secret token, 400 on invalid JSON
```

```bash
TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set \
    --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
```

## Documentation

- **[Installation](INSTALLATION.md)**: setup and your first bot
- **[API Reference](API_REFERENCE.md)**: framework and method reference
- **[Advanced Features](ADVANCED_FEATURES.md)**: middleware, conversations, caching, plugins
- **[Security Guide](SECURITY.md)**: security best practices
- **[Deployment](DEPLOYMENT.md)**: production deployment
- **[Testing](TESTING.md)**: running and writing tests
- **[Changelog](CHANGELOG.md)**

## Requirements

- PHP 8.4 or higher
- cURL and JSON extensions
- An HTTPS server (webhooks only)

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan   # static analysis
```

## Author

LightYagami28 (ceo@retechrevive.it)

## License

MIT License - See LICENSE file
