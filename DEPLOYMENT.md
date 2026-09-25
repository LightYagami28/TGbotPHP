# Deployment Guide

## Prerequisites

- PHP 8.2+ with cURL extension
- HTTPS-enabled server (required by Telegram)
- Composer
- Domain with SSL certificate

## Shared Hosting

### Step 1: Upload Files

1. Clone repository or upload files via FTP
2. Ensure `src/`, `vendor/`, and `public/` are readable

### Step 2: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### Step 3: Create Webhook Handler

Create `public/webhook.php` (see [`examples/webhook.php`](examples/webhook.php) for a complete bot):

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),
));
$bot->setUsername(getenv('TELEGRAM_BOT_USERNAME') ?: null);

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Welcome!'));

$bot->handle();
```

### Step 4: Configure Environment

Set the variables in your hosting panel or web server configuration. Keep them out of the web root.

```
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_SECRET_TOKEN=a_long_random_string
TELEGRAM_BOT_USERNAME=your_bot
```

### Step 5: Set Webhook

```bash
TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set \
  --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
```

## Docker Deployment

The image runs the long polling example by default. It needs no public HTTPS endpoint.

### Step 1: Build Image

```bash
docker build -t tgbotphp:latest .
```

### Step 2: Run Container

```bash
docker run -d --restart unless-stopped \
  -e TELEGRAM_BOT_TOKEN=your_token \
  --name tgbot \
  tgbotphp:latest php examples/polling.php
```

## VPS/Cloud Deployment

### Using Systemd Service (long polling)

Create `/etc/systemd/system/tgbot.service`:

```ini
[Unit]
Description=TGbotPHP Bot Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/tgbot
Environment=TELEGRAM_BOT_TOKEN=your_token
ExecStart=/usr/bin/php bot.php
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable tgbot
sudo systemctl start tgbot
```

### Using Nginx

Create `/etc/nginx/sites-available/tgbot`:

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/tgbot/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable:

```bash
sudo ln -s /etc/nginx/sites-available/tgbot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Database Setup (Optional)

For bots needing persistence:

### MySQL/MariaDB

```bash
mysql -u root -p < database.sql
```

```php
$pdo = new PDO('mysql:host=localhost;dbname=tgbot', 'user', 'password');
```

### SQLite

```php
$pdo = new PDO('sqlite:/var/www/tgbot/database.sqlite');
```

## SSL Certificate

### Let's Encrypt

```bash
sudo certbot certonly --standalone -d example.com
```

Renew automatically:

```bash
sudo certbot renew --quiet
```

## Monitoring

### Log Files

```bash
tail -f /var/log/tgbot.log
```

### Health Check

```php
$bot->on('update.received', function (stdClass $update) {
    error_log('[UPDATE] ' . $update->update_id);
});

$bot->onError(function (Throwable $e, ?stdClass $update) {
    error_log('[ERROR] ' . $e->getMessage());
});
```

Check delivery errors reported by Telegram with `vendor/bin/tgbot webhook:info`.

## Security Hardening

### 1. Use Secret Token

Set webhook secret token:

```bash
curl -X POST https://api.telegram.org/botTOKEN/setWebhook \
  -d url=https://example.com/webhook.php \
  -d secret_token=your_secret_token
```

### 2. Validate Requests

`$bot->handle()` validates the `X-Telegram-Bot-Api-Secret-Token` header when `Config::$secretToken` is set. It answers 403 otherwise. To check the source IP as well:

```php
use TGbotPHP\Security\WebhookValidator;

if (!WebhookValidator::isTelegramIp($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(403);
    exit;
}
```

### 3. Environment Variables

Use `.env` for sensitive data:

```bash
chmod 600 .env
```

```php
$token = getenv('TELEGRAM_BOT_TOKEN');
```

### 4. Firewall Rules

Allow only Telegram IPs:

```bash
sudo ufw allow from 149.154.160.0/20 to any port 443
sudo ufw allow from 91.108.4.0/22 to any port 443
```

## Scaling

### Multiple Instances

Use load balancer (nginx, HAProxy):

```nginx
upstream tgbot {
    server bot1.local:8000;
    server bot2.local:8000;
    server bot3.local:8000;
}

server {
    listen 443 ssl;
    location / {
        proxy_pass http://tgbot;
    }
}
```

### Shared Cache

With several instances, `FileCache` is not shared between servers. Implement `TGbotPHP\Cache\CacheInterface` on top of Redis or your database, then pass it to `RateLimiter` and `useConversations()`.

## Troubleshooting

### Webhook Not Working

1. Check certificate validity
2. Verify HTTPS is enabled
3. Test webhook: `curl -v https://example.com/webhook.php`
4. Check logs for errors

### Bot Not Responding

1. Verify the token: `vendor/bin/tgbot bot:info`
2. Check webhook status: `vendor/bin/tgbot webhook:info`
3. Review error logs

### High Memory Usage

1. Enable caching
2. Implement rate limiting
3. Monitor long-running processes

## Backup & Recovery

### Regular Backups

```bash
tar czf backup-$(date +%Y%m%d).tar.gz /var/www/tgbot/
```

### Database Backups

```bash
mysqldump -u user -p tgbot > backup.sql
```

## Performance Tips

1. Run `composer install --no-dev --optimize-autoloader`
2. Use a shared cache for multi-server setups
3. Implement session persistence
4. Optimize database queries
5. Use CDN for static files
