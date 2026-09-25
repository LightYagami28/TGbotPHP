# TGbotPHP

TGbotPHP is a PHP library for the [Telegram Bot API](https://core.telegram.org/bots/api). It gives you a typed client for every API method and a small framework on top: routing, conversations, middleware, webhooks and long polling.

```php
use TGbotPHP\Framework\Bot;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello!'));

$bot->poll();
```

## Why TGbotPHP

- **All of Bot API 10.3.** The 185 methods and 27 update types are implemented, and a test checks each one against the official documentation. Methods Telegram adds later work through `call()`, and new parameters through `$options`.
- **No runtime dependencies.** Only the `curl` and `json` extensions.
- **Safe by default.** Webhook secret tokens are compared in constant time. Uploads never read a file unless you ask for it. The debug log redacts secrets.
- **Correct under load.** Conversations and rate limits use atomic cache updates, so concurrent webhook requests do not overwrite each other.
- **Testable.** `BotTester` runs your handlers without network access.
- **Checked.** PHPStan at level 10 with strict rules, 97% line coverage, and end-to-end tests against the real API.

## Guide

| Page | Covers |
|---|---|
| [Getting Started](Getting-Started) | Installation, your first bot with long polling and with a webhook |
| [Handling Updates](Handling-Updates) | Commands, text patterns, callbacks, inline queries, other update types |
| [API Reference](API-Reference) | Calling API methods, options, files, errors, every method group |
| [Keyboards and Callbacks](Keyboards-and-Callbacks) | Inline and reply keyboards, buttons, formatting |
| [Conversations](Conversations) | Multi-step dialogs, caches, sessions, rate limiting |
| [Middleware and Events](Middleware-and-Events) | Middleware, events, error handling, plugins |
| [Configuration](Configuration) | `Config`, retries, debug log, local Bot API server, `BotBuilder`, CLI |
| [Deployment](Deployment) | Webhooks in production, long polling as a service, Docker |
| [Security Guide](Security-Guide) | Tokens, webhook secrets, Mini App data, user input |
| [Testing](Testing) | Testing your bot without network access |
| [Examples](Examples) | Complete bots |
| [Upgrading from 2.x](Upgrading-from-2.x) | Breaking changes in 3.0 |
| [FAQ](FAQ) and [Troubleshooting](Troubleshooting) | Common questions and problems |
| [Changelog](Changelog) | Release history |

## Requirements

- PHP 8.4 or later, with the `curl` and `json` extensions
- A public HTTPS URL, only if you receive updates through a webhook

## Links

- [Repository](https://github.com/LightYagami28/TGbotPHP)
- [Issues](https://github.com/LightYagami28/TGbotPHP/issues)
- [Security policy](https://github.com/LightYagami28/TGbotPHP/blob/main/SECURITY.md)
- License: [Apache 2.0](https://github.com/LightYagami28/TGbotPHP/blob/main/LICENSE)
