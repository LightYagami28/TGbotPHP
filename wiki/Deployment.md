# Deployment

| | Webhook | Long polling |
|---|---|---|
| How | Telegram sends each update to your HTTPS URL | A PHP process asks Telegram for updates in a loop |
| Needs | A public HTTPS URL | Outbound network access only |
| Runs on | Any PHP web hosting, PHP-FPM, serverless | A server or container that keeps a process running |
| State | Persistent cache (`FileCache`, Redis...) | `ArrayCache` is enough |

Only one of them can be active: Telegram refuses `getUpdates` (409 Conflict) while a webhook is set.

## Webhook

### 1. Install

```bash
composer install --no-dev --optimize-autoloader
```

Only the entry point directory (`public/`) should be reachable over HTTP, never `vendor/`, `src/` or the cache directory.

### 2. Entry point

`public/webhook.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Cache\FileCache;
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),
));
$bot->setUsername('my_bot');                                  // so /start@other_bot is ignored
$bot->useConversations(new FileCache('/var/lib/mybot/cache'));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Welcome!'));

$bot->onError(fn(Throwable $e) => error_log('[bot] ' . $e->getMessage()));

$bot->handle();
```

`handle()`:

- answers **403** when the `X-Telegram-Bot-Api-Secret-Token` header does not match the secret;
- answers **400** to a body that is not valid JSON;
- otherwise processes the update and answers **200**, even when a handler fails: an error status would make Telegram send the same update again and again.

**Slow handlers.** Telegram waits for the response before sending the next update of the same chat. On PHP-FPM or LiteSpeed, `$bot->handle(respondFirst: true)` answers Telegram at once, then runs the handlers. Handlers must then not rely on the HTTP output, which is no longer sent.

### 3. Environment

Set the secrets in the web server or the hosting panel, never in a file under the web root:

```
TELEGRAM_BOT_TOKEN=123456789:AA...
TELEGRAM_SECRET_TOKEN=a long random string    # openssl rand -hex 32
```

### 4. Register the webhook

```bash
vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
vendor/bin/tgbot webhook:info
```

`webhook:info` shows the pending updates and the last delivery error. Add `--drop-pending` to discard the updates that arrived while the bot was down.

From code, with more options:

```php
use TGbotPHP\Support\Value;

$bot->setWebhook(
    url: 'https://example.com/webhook.php',
    maxConnections: 40,
    allowedUpdates: ['message', 'callback_query', 'chat_member'],
    secretToken: Value::env('TELEGRAM_SECRET_TOKEN'),
);
```

Telegram only calls ports 443, 80, 88 and 8443.

### Nginx and PHP-FPM

```nginx
server {
    listen 443 ssl;
    server_name example.com;

    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/bot/public;

    location = /webhook.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/webhook.php;
        fastcgi_param TELEGRAM_BOT_TOKEN "...";
        fastcgi_param TELEGRAM_SECRET_TOKEN "...";
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location / {
        return 404;
    }
}
```

Certificates from Let's Encrypt work: `certbot certonly --nginx -d example.com`.

### Apache

```apache
<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/bot/public
    SetEnv TELEGRAM_BOT_TOKEN "..."
    SetEnv TELEGRAM_SECRET_TOKEN "..."
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem
</VirtualHost>
```

With `SetEnv`, read the values with `$_SERVER['TELEGRAM_BOT_TOKEN']` if `getenv()` returns `false` on your setup.

## Long polling

```bash
vendor/bin/tgbot webhook:delete
php bot.php
```

`poll()`:

- keeps a connection open for up to 30 seconds waiting for updates (`timeout:`), so updates arrive at once without hammering Telegram;
- retries network and server errors, waiting 1, 2, 4... up to 30 seconds;
- waits as long as Telegram asks after a 429;
- stops on errors that retrying cannot fix: an invalid token (401) or a webhook still set (409);
- confirms the processed updates when it stops, so they are not delivered again.

Stop it cleanly with `$bot->stop()`, for example on `SIGTERM` with the `pcntl` extension:

```php
pcntl_async_signals(true);
pcntl_signal(SIGTERM, fn() => $bot->stop());
pcntl_signal(SIGINT, fn() => $bot->stop());

$bot->poll();
```

Run only one polling process per bot: Telegram does not split updates between processes.

### systemd

`/etc/systemd/system/mybot.service`:

```ini
[Unit]
Description=Telegram bot
After=network-online.target
Wants=network-online.target

[Service]
User=bot
WorkingDirectory=/opt/mybot
Environment=TELEGRAM_BOT_TOKEN=...
ExecStart=/usr/bin/php bot.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now mybot
journalctl -u mybot -f
```

### Docker

The repository's `Dockerfile` builds an image with only the runtime files, running as an unprivileged user. It starts the long polling example; mount your bot and override the command:

```bash
docker build -t mybot .
docker run -d --restart unless-stopped \
    -e TELEGRAM_BOT_TOKEN=... \
    -v "$PWD/bot.php:/app/bot.php:ro" \
    mybot php bot.php
```

`docker-compose.yml` has a `bot` service for long polling and a `webhook` profile behind your HTTPS reverse proxy.

## Checklist

- [ ] The token and the webhook secret come from the environment, not from files in the repository
- [ ] `webhook:info` shows no delivery error
- [ ] Conversations and rate limits use a persistent cache with webhooks
- [ ] `onError()` logs errors somewhere you read
- [ ] The debug log is off
- [ ] `vendor/`, `src/` and the cache directory are not reachable over HTTP
- [ ] A test checks your main handlers (see [Testing](Testing))
