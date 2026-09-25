# Deployment

A bot receives updates in one of two ways:

- **Webhook:** Telegram sends each update to your HTTPS URL. It needs a public HTTPS endpoint and runs on any PHP hosting.
- **Long polling:** a long-running process asks Telegram for updates. It needs no public endpoint, but it needs a process manager (systemd, Docker).

## Webhook

### 1. Install

```bash
composer install --no-dev --optimize-autoloader
```

Only `public/` (or wherever your entry point is) should be reachable over HTTP, never `vendor/`, `src/` or the cache directory.

### 2. Entry point

`public/webhook.php` (see [`examples/webhook.php`](../examples/webhook.php) for a complete bot):

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;

$bot = new Bot(new Config(
    token: Value::env('TELEGRAM_BOT_TOKEN') ?? '',
    secretToken: Value::env('TELEGRAM_SECRET_TOKEN') ?? false,
));
$bot->setUsername(Value::env('TELEGRAM_BOT_USERNAME'));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Welcome!'));

$bot->handle();
```

Telegram waits for the response before it sends the next update of the same chat. On PHP-FPM or LiteSpeed, `$bot->handle(respondFirst: true)` answers Telegram at once and then runs the handlers, so slow handlers (large uploads, external APIs) do not hold updates back. Handlers must not rely on the HTTP output, which is no longer sent.

### 3. Environment

Set these in the web server or hosting panel, not in a file under the web root:

```
TELEGRAM_BOT_TOKEN=...
TELEGRAM_SECRET_TOKEN=a long random string   # openssl rand -hex 32
TELEGRAM_BOT_USERNAME=your_bot
```

### 4. Register the webhook

```bash
TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set \
    --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
vendor/bin/tgbot webhook:info
```

### Nginx

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

To also restrict requests to Telegram's servers:

```php
use TGbotPHP\Security\WebhookValidator;

if (!WebhookValidator::isTelegramIp($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(403);
    exit;
}
```

Behind a reverse proxy, `REMOTE_ADDR` is the proxy: rely on the secret token instead.

## Long polling

Remove any webhook first, otherwise `getUpdates` fails with `409 Conflict`:

```bash
TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:delete
```

### systemd

`/etc/systemd/system/telegram-bot.service`:

```ini
[Unit]
Description=Telegram bot
After=network-online.target
Wants=network-online.target

[Service]
User=bot
WorkingDirectory=/opt/bot
Environment=TELEGRAM_BOT_TOKEN=...
ExecStart=/usr/bin/php bot.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now telegram-bot
journalctl -u telegram-bot -f
```

With the `pcntl` extension, handle `SIGTERM` to stop cleanly between updates (see [`examples/polling.php`](../examples/polling.php)).

### Docker

The image runs the long polling example as an unprivileged user:

```bash
docker build -t tgbotphp .
docker run -d --restart unless-stopped -e TELEGRAM_BOT_TOKEN=... tgbotphp
```

Mount your own bot script and override the command to run it.

## State and scaling

- `ArrayCache` only lives as long as the process: use it with long polling.
- With webhooks, each request is a new process: use `FileCache` (one server) or your own `CacheInterface` on Redis or a database (several servers) for conversations and rate limiting.
- Run only one long polling process per bot: Telegram does not split updates between pollers.

## Troubleshooting

| Symptom | Check |
|---|---|
| The bot does not answer | `vendor/bin/tgbot bot:info` validates the token |
| Webhook updates do not arrive | `vendor/bin/tgbot webhook:info` shows the last delivery error |
| `409 Conflict` when polling | A webhook is still set: `tgbot webhook:delete` |
| `403` from your webhook | The secret token registered with `webhook:set` differs from `TELEGRAM_SECRET_TOKEN` |
| Handler errors | They are sent to `onError()`, or written to the PHP error log |
