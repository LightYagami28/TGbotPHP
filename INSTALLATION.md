# Installation Guide

## Requirements

- PHP 7.0+ (8.4+ recommended)
- cURL extension enabled
- HTTPS support for webhooks

## Installation via Composer

```bash
composer require lightyagami28/tgbotphp
```

## Setup

### 1. Get Telegram Bot Token

- Open Telegram and chat with [@BotFather](https://t.me/botfather)
- Send `/newbot`
- Follow the instructions
- Copy your token

### 2. Environment Configuration

Create `.env` file:

```env
TELEGRAM_BOT_TOKEN=your_bot_token_here
DEBUG_MODE=false
DEBUG_LOG_FILE=/var/log/telegram-bot.log
TELEGRAM_SECRET_TOKEN=optional_secret
```

### 3. Create Webhook Script

`webhook.php`:

```php
<?php
declare(strict_types=1);

use TGbotPHP\Framework\Bot;

require_once 'vendor/autoload.php';

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

// Register handlers
$bot->command('/start', fn($u) => 
    $bot->sendMessage($u->message->chat->id, "Hello!")
);

// Handle update
$bot->handleUpdate(file_get_contents('php://input'));
```

### 4. Register Webhook

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://your-domain.com/webhook.php"
```

### 5. Verify Setup

```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

## Long Polling Alternative

```php
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;

$config = new Config(getenv('TELEGRAM_BOT_TOKEN'));
$client = new ApiClient($config);

while (true) {
    $updates = $client->getUpdates();
    
    foreach ($updates as $update) {
        // Process update
    }
    
    sleep(1);
}
```

## Directory Structure

```
your-project/
├── .env
├── webhook.php
├── vendor/
└── your-bot-code.php
```

## Quick Example

```php
<?php
use TGbotPHP\Framework\Bot;

$bot = new Bot($_ENV['TELEGRAM_BOT_TOKEN']);

$bot->command('/start', function($update) use ($bot) {
    $bot->sendMessage(
        $update->message->chat->id,
        "Welcome to my bot!"
    );
});

$bot->callback('my_button', function($update) use ($bot) {
    $bot->editMessageText(
        $update->callback_query->message->chat->id,
        $update->callback_query->message->message_id,
        "Button clicked!"
    );
});

$bot->handleUpdate(file_get_contents('php://input'));
```

## Security

- Store token in environment variables
- Never commit `.env` file
- Always use HTTPS for webhooks
- Validate webhook IP addresses
- Use secret tokens for extra security

## Troubleshooting

### Bot not responding

1. Check webhook URL is HTTPS
2. Verify token is correct
3. Check server error logs
4. Ensure cURL extension is installed

### Webhook issues

```bash
# Check webhook status
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"

# Reset webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/deleteWebhook"
```

### Enable debug mode

```php
$bot = new Bot(
    token: $token,
    debug: true,
    debugFile: '/var/log/telegram-bot.log'
);
```

## Next Steps

- Read [API Reference](https://github.com/LightYagami28/TGbotPHP/wiki/API-Reference)
- Check [Examples](https://github.com/LightYagami28/TGbotPHP/wiki/Examples)
- Review [Security Guide](https://github.com/LightYagami28/TGbotPHP/wiki/Security-Guide)
