# Security Policy

## Reporting Vulnerabilities

Please do not open public issues for security problems. Report them privately with [GitHub Security Advisories](https://github.com/LightYagami28/TGbotPHP/security/advisories/new) or by email to ceo@retechrevive.it.

## Built-in protections

| Protection | Where |
|---|---|
| Webhook secret token checked in constant time | `Bot::handle()`, `WebhookValidator::validate()` |
| Token format validation (no path injection in API URLs) | `Config` |
| HTTPS-only API server and webhook URLs | `Config`, `setWebhook()` |
| TLS certificate and host verification | `CurlTransport` |
| Uploads only through explicit `InputFile` objects: a string from a user is never read as a local path | media methods |
| Telegram IP range check | `WebhookValidator::isTelegramIp()` |
| Mini App `initData` HMAC and expiry check | `WebhookValidator::validateWebAppData()` |
| HTML / MarkdownV2 escaping | `Formatter` |
| No object deserialization from the cache | `FileCache` |
| Log injection prevention | `Logger` |
| Per-user rate limiting | `RateLimiter::middleware()` |
| Secrets redacted from the debug log | `HttpClientTrait` |
| Immutable configuration | `Config` (readonly properties) |
| No redirects, HTTP(S) only | `CurlTransport` |
| API results validated against the declared type | `apiCall*()` wrappers |

## Best Practices

### 1. Webhook secret token

```php
$bot = new Bot(new Config(getenv('TELEGRAM_BOT_TOKEN'), secretToken: getenv('TELEGRAM_SECRET_TOKEN')));
$bot->handle(); // answers 403 when the header does not match
```

Register the same secret with `vendor/bin/tgbot webhook:set --url=... --secret=...`.

### 2. Escape user input

```php
$bot->reply($message, 'Hello ' . Formatter::escape($message->from->first_name));
```

### 3. Rate limiting

```php
$bot->middleware((new RateLimiter($cache))->middleware(10, 60));
```

### 4. Secrets

- Keep the bot token in environment variables, never in the repository
- The download URL returned by `getFileUrl()` contains the token: never show it to users
- Keep `FileCache` directories and debug logs outside the web root. Debug logs contain request parameters.

### 5. HTTPS only

Telegram requires HTTPS for webhooks. Only disable `Config::$enforceHttps` for a local Bot API server on a trusted network.

## Dependencies

TGbotPHP has no runtime dependencies. Dependabot keeps the GitHub Actions up to date.
