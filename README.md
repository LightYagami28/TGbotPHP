# TGbotPHP

PHP library for the [Telegram Bot API](https://core.telegram.org/bots/api), with routing, middleware and conversations for building bots.

[![Tests](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml)
[![Code Analysis](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777bb4)](https://www.php.net/)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache--2.0-blue)](LICENSE)

Fork of [OpenTelegramFiles/TGbotPHP](https://github.com/OpenTelegramFiles/TGbotPHP).

## Requirements

- PHP 8.4 or later, with the `curl` and `json` extensions
- An HTTPS endpoint, only if you receive updates through a webhook

There are no runtime dependencies.

## Installation

```bash
composer require lightyagami28/tgbotphp
```

## Example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Framework\Bot;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\Keyboard;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

// Handlers receive the payload, the bot, then data from the route
$bot->command('start', function (stdClass $message, Bot $bot, string $payload): void {
    $bot->reply($message, 'Hello ' . Formatter::bold($message->from->first_name), [
        'reply_markup' => Keyboard::inline(['Yes' => 'vote:yes', 'No' => 'vote:no']),
    ]);
});

$bot->callback('vote:*', function (stdClass $callback, Bot $bot, array $matches): void {
    $bot->answer($callback, "You voted {$matches[1]}");
});

$bot->poll();      // long polling
// $bot->handle(); // or behind a webhook
```

[`examples/`](examples) has a [long polling bot](examples/polling.php) and a [webhook bot](examples/webhook.php) with conversations and rate limiting.

## What it covers

- **API client:** every method of Bot API 10.3 (checked against the official documentation by the tests), `call()` for methods Telegram adds later, and an `$options` array on sending methods for optional parameters. Results are checked against the type each method declares.
- **Files:** `InputFile` uploads local files or in-memory contents, including albums and sticker sets. `downloadFile()` fetches them back.
- **Routing:** commands (including `/cmd@bot` and deep-link payloads), text patterns, callback and inline query patterns (exact, `prefix:*` or a regex), and handlers for any update type.
- **Testing:** `TGbotPHP\Testing\BotTester` and `FakeUpdate` test your handlers without network access.
- **Updates:** `handle()` for webhooks, with secret token check. `poll()` for long polling, with backoff on errors.
- **Middleware, events and conversations:** multi-step dialogs with state per user.
- **Errors:** typed exceptions (`ApiException`, `TooManyRequestsException`, `NetworkException`), automatic retries after 429 responses, and an `onError()` hook.
- **Security helpers:** webhook secret token, Telegram IP ranges, Mini App `initData` validation, HTML and MarkdownV2 escaping, per-user rate limiting.

## Documentation

- [Installation](docs/installation.md)
- [API reference](docs/api-reference.md)
- [Advanced usage](docs/advanced.md): middleware, conversations, cache, plugins, errors
- [Architecture](docs/architecture.md)
- [Deployment](docs/deployment.md)
- [Testing](docs/testing.md)
- [Development](docs/development.md)
- [Security](SECURITY.md)
- [Changelog](CHANGELOG.md)

## Development

```bash
composer install
composer test      # unit tests
composer phpstan   # static analysis, level 10
```

End-to-end tests against the real API are described in [docs/testing.md](docs/testing.md).

## License

Apache License 2.0, see [LICENSE](LICENSE). This project is a fork of [OpenTelegramFiles/TGbotPHP](https://github.com/OpenTelegramFiles/TGbotPHP), released under the same license.
