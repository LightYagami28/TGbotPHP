# Specification

## Purpose

TGbotPHP is a PHP library (Composer package `lightyagami28/tgbotphp`, namespace `TGbotPHP\`) for building Telegram bots on the [Bot API](https://core.telegram.org/bots/api). It provides a typed API client, update routing, middleware, conversations, webhook and long polling runners, and test helpers. It has no runtime dependencies beyond `ext-curl` and `ext-json`.

## Features

- Every Bot API 10.3 method, with parameter names and result types checked against the official documentation by `BotApiCoverageTest`; `call()` for methods added later.
- File upload (`InputFile`, albums, stickers) and `downloadFile()`.
- Routing of commands (with `/cmd@bot` and deep-link payload), text patterns, callbacks, inline queries and any update type.
- Middleware pipeline, event dispatcher, plugins, per-user sessions and multi-step conversations.
- Webhook runner (`handle()`) and long polling runner (`poll()`), with backoff on errors.
- Typed exceptions, 429 handling with `retry_after`, configurable retry policy.
- Security helpers: secret token, Telegram IP ranges, Mini App `initData` validation, escaping, rate limiting, log redaction.
- Testing with `BotTester`, `FakeUpdate` and `FakeTransport`, without network.
- CLI `bin/tgbot`: `webhook:info`, `webhook:set`, `webhook:delete`, `bot:info`, `bot:test`, `commands:list`, `commands:delete`.

## Architecture

Three layers, see [docs/architecture.md](docs/architecture.md):

1. `Framework\Bot` (facade), `Kernel`, `Router`, `MiddlewarePipeline`, `EventDispatcher`, runners `WebhookHandler` and `LongPolling`.
2. `Core\ApiClient` with one trait per API area in `Methods\`, and `Traits\HttpClientTrait` (encoding, error mapping, retries).
3. `Http\TransportInterface`, implemented by `CurlTransport`; the only I/O boundary, replaced by `Testing\FakeTransport` in tests.

## Main flows

- **Outgoing call:** a method trait builds the parameters, `HttpClientTrait` encodes them (JSON or multipart when files are present), the transport sends the request, the response is decoded whatever the HTTP status, errors become typed exceptions, the result is checked against the declared type.
- **Webhook:** `handle()` checks method, secret token (constant time) and body size, parses the update, runs the `Kernel`, answers 200 (or 500 if `acknowledgeHandlerErrors` is false).
- **Long polling:** `poll()` calls `getUpdates` with an offset, runs each update through the `Kernel`, backs off on network errors and 429.
- **Update handling:** events, then middleware, then the first matching route, then conversation state if one is active for the user and chat.

## Data model

- Updates and API results are decoded as `stdClass` objects, as Telegram sends them; `Support\Value` and `Support\Payload` give typed reads.
- `Core\Config` is immutable (readonly): token, secret token, API base URL, HTTPS enforcement, timeout, `RetryPolicy`, debug log, `allowUnauthenticatedWebhook`, `maxWebhookBodyBytes`, `acknowledgeHandlerErrors`, `localFileRoot`.
- State (sessions, conversations, rate limits) lives behind `Cache\CacheInterface`: `ArrayCache` (process memory) or `FileCache` (files readable only by the owner, no object deserialization).

## Interfaces

- Public PHP API: `Bot`, `ApiClient`, `Config`, `Keyboard`, `InlineKeyboard`, `Formatter`, `InputFile`, `RateLimiter`, `WebhookValidator`, `BotTester`. Reference in [docs/api-reference.md](docs/api-reference.md).
- Extension points: `TransportInterface`, `CacheInterface`, `PluginInterface`, `BotPluginInterface`, middleware callables.
- Environment variables used by examples and Docker: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_SECRET_TOKEN`, `TELEGRAM_BOT_USERNAME` (see [.env.example](.env.example)).
- Network: outgoing HTTPS to the Bot API server (default `https://api.telegram.org`); incoming HTTPS webhook requests from Telegram.

## Stack and versions

| Component | Version |
|---|---|
| PHP | 8.5 or later (CI on 8.5, 8.6 experimental) |
| Extensions | `curl`, `json`; `sodium` optional (Mini App validation without the token) |
| PHPUnit | ^13.4 |
| PHPStan | ^2.3 (+ phpstan-phpunit ^2.1, phpstan-strict-rules ^2.1), level 10 |
| Code style | PER-CS 2.0 with php-cs-fixer |
| Docker | `php:8.5-cli`, multi-stage, non-root user |
| GitHub Actions | pinned by commit SHA with the tag in a comment; Dependabot updates them weekly |

## Security

See [SECURITY.md](SECURITY.md). Summary: webhooks without a secret are refused; token format validated; HTTPS and TLS verification enforced by default; no redirects; uploads only through explicit `InputFile`; secrets redacted from logs; log injection prevented; containers run read-only, without capabilities, as a non-root user. Secrets only through environment variables, never in files.

## Known limits

- No built-in persistence beyond `FileCache`; running several instances needs a shared `CacheInterface` implementation.
- `ArrayCache` loses state on restart and does not work across PHP-FPM requests.
- Updates and results are `stdClass`, not generated typed classes.
- Some file-permission tests assume POSIX and fail on Windows.
- Telegram limits (rate, file size) apply and are not enforced locally except by `RateLimiter` per user.

## Roadmap

- Follow new Bot API releases (the coverage test flags differences).
- Typed value objects for updates and results.
- Cache backends for shared storage (Redis, database).
- PHP 8.6 as a required CI target when it is stable.
