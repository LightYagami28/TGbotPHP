# Security Guide

## The bot token

Anyone with the token controls the bot: they can read its updates and send messages in its name.

- Read it from the environment (`getenv('TELEGRAM_BOT_TOKEN')`), never write it in the code or commit it.
- Pass it to the CLI through `TELEGRAM_BOT_TOKEN` rather than `--token=`, which ends up in the shell history.
- `getApiUrl()` and `getFileUrl()` contain the token: never show them to users or log them.
- If the token leaks, revoke it at once: @BotFather → `/mybots` → your bot → *API Token* → *Revoke current token*.

The library checks the shape of the token and never writes it to the debug log.

## Webhooks

Anyone can send a request to your webhook URL. Without protection, they could impersonate any user.

**Use a secret token.** Telegram sends it in the `X-Telegram-Bot-Api-Secret-Token` header of each request, and `handle()` rejects the others with 403. The comparison takes constant time, so the secret cannot be guessed by measuring response times.

```php
$bot = new Bot(new Config(token: $token, secretToken: getenv('TELEGRAM_SECRET_TOKEN')));
$bot->handle();
```

```bash
vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
```

Generate the secret with `openssl rand -hex 32`. It may contain `A-Z a-z 0-9 _ -`, up to 256 characters.

**Optionally, check the IP address** as well. Telegram sends webhooks from `149.154.160.0/20` and `91.108.4.0/22`:

```php
use TGbotPHP\Security\WebhookValidator;

if (!WebhookValidator::isTelegramIp($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(403);
    exit;
}
```

Behind a reverse proxy or a CDN, `REMOTE_ADDR` is the proxy's address. Do not trust `X-Forwarded-For` unless your proxy sets it; rely on the secret token.

## User input

Everything in an update comes from users and may be crafted.

**Escape text in HTML messages.** Messages use HTML formatting by default. An unescaped `<` makes Telegram reject the message, and user text could add links to your messages:

```php
use TGbotPHP\Utilities\Formatter;

$bot->reply($message, 'You said: ' . Formatter::escape($message->text));
```

Use `Formatter::escapeMarkdownV2()` with MarkdownV2, or `parseMode: null` for plain text.

**Check permissions for callback queries.** Clients can send any `callback_data`, not only the data of the buttons you sent. Before acting on `delete:42`, check that the user owns item 42.

**Check who sent administrative commands.** A command handler runs for anyone who writes the command:

```php
$bot->command('broadcast', function (stdClass $message, Bot $bot, string $text) use ($admins): void {
    if (!in_array($message->from->id, $admins, true)) {
        return;
    }
    // ...
});
```

**Files.** A plain string is always sent as a `file_id` or a URL: the library only reads a local file when you wrap it in `InputFile::fromPath()`. Never pass a path built from user input to `InputFile::fromPath()` or to `downloadFile()`.

**Rate limits.** Protect expensive handlers from floods with the [rate limiter](Conversations#rate-limiting).

## Mini Apps

A Mini App sends `Telegram.WebApp.initData` to your server. It says who the user is, but it comes from the browser: validate it before trusting it.

With the bot token:

```php
use TGbotPHP\Security\WebhookValidator;

$data = WebhookValidator::validateWebAppData($_POST['initData'] ?? '', $token, maxAge: 3600);

if ($data === null) {
    http_response_code(403);
    exit;
}

$user = json_decode($data['user'], true, flags: JSON_THROW_ON_ERROR);
```

Without the token, for a service that receives the data of a bot it does not own. Telegram signs the data with Ed25519, and the check needs only the bot id (requires the `sodium` extension):

```php
$data = WebhookValidator::validateWebAppSignature($initData, botId: 123456789, maxAge: 3600);
```

Pass `publicKey: WebhookValidator::WEB_APP_TEST_PUBLIC_KEY` for bots of the test environment.

`maxAge` rejects data older than the given number of seconds, so a stolen `initData` cannot be reused forever.

## Storage

- `FileCache` stores values with `serialize()` but never restores objects, so a tampered cache file cannot inject objects. Its files are created readable by the bot's user only (0600). Keep the cache directory outside the web root.
- The debug log contains messages. It is created readable by the bot's user only, replaces `secret_token` and `provider_token` with `<redacted>`, but keep it private and off in production.

## The library itself

- TLS certificates are verified, and cURL only speaks HTTP(S) and never follows redirects.
- `composer audit` runs in CI every day; GitHub Actions are pinned to commit SHAs; Dependabot keeps the dependencies current.
- The Docker image runs as an unprivileged user and contains no tests, docs or development tools.

## Reporting a vulnerability

Do not open a public issue. Follow the [security policy](https://github.com/LightYagami28/TGbotPHP/blob/main/SECURITY.md).
