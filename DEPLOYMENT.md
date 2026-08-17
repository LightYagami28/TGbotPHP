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

Create `public/webhook.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Utilities\BotBuilder;

$bot = (new BotBuilder(getenv('BOT_TOKEN')))
    ->addCommand('start', function($bot, $msg) {
        $bot->sendMessage(
            chatId: $msg['chat']['id'],
            text: 'Welcome!'
        );
    })
    ->build();

$bot->handle();
```

### Step 4: Configure Environment

Create `.env`:

```
BOT_TOKEN=your_bot_token_here
WEBHOOK_URL=https://example.com/webhook.php
DEBUG=false
```

### Step 5: Set Webhook

```bash
curl -X POST https://api.telegram.org/botYOUR_TOKEN/setWebhook \
  -d url=https://example.com/webhook.php
```

## Docker Deployment

### Step 1: Build Image

```bash
docker build -t tgbotphp:latest .
```

### Step 2: Run Container

```bash
docker run -d \
  -e BOT_TOKEN=your_token \
  -e WEBHOOK_URL=https://example.com/webhook.php \
  -p 8000:8000 \
  --name tgbot \
  tgbotphp:latest
```

### Step 3: Docker Compose

```bash
docker-compose up -d
```

## VPS/Cloud Deployment

### Using Systemd Service

Create `/etc/systemd/system/tgbot.service`:

```ini
[Unit]
Description=TGbotPHP Bot Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/tgbot
ExecStart=/usr/bin/php -S 0.0.0.0:8000 -t public/
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
$bot->on('update', function($update) {
    error_log('[UPDATE] ' . json_encode($update));
});
```

## Security Hardening

### 1. Use Secret Token

Set webhook secret token:

```bash
curl -X POST https://api.telegram.org/botTOKEN/setWebhook \
  -d url=https://example.com/webhook.php \
  -d secret_token=your_secret_token
```

### 2. Validate Requests

```php
$xToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
if (!WebhookValidator::validate($body, $secretToken, $xToken)) {
    exit('Unauthorized');
}
```

### 3. Environment Variables

Use `.env` for sensitive data:

```bash
chmod 600 .env
```

```php
$token = getenv('BOT_TOKEN');
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

### Redis Caching

```php
$cache = new RedisCache(new Redis());
$limiter = new RateLimiter($cache);
```

## Troubleshooting

### Webhook Not Working

1. Check certificate validity
2. Verify HTTPS is enabled
3. Test webhook: `curl -v https://example.com/webhook.php`
4. Check logs for errors

### Bot Not Responding

1. Verify BOT_TOKEN is correct
2. Check webhook status: `curl https://api.telegram.org/botTOKEN/getWebhookInfo`
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

1. Enable Zstandard compression in Composer
2. Use Redis for caching
3. Implement session persistence
4. Optimize database queries
5. Use CDN for static files
