# Installation Guide

## Requirements

- PHP 8.4 or higher
- cURL and JSON extensions
- An HTTPS server, for webhooks only (long polling works anywhere)

## Installation via Composer

```bash
composer require lightyagami28/tgbotphp
```

## Setup

### 1. Get a bot token

1. Open [@BotFather](https://t.me/botfather) in Telegram
2. Send `/newbot` and follow the instructions
3. Copy the token (`123456789:AA...`) and keep it secret

### 2. Configure the environment

```bash
export TELEGRAM_BOT_TOKEN=123456789:AA...
export TELEGRAM_SECRET_TOKEN=$(openssl rand -hex 32)   # webhooks only
```

### 3a. Long polling (simplest)

`bot.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Framework\Bot;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello!'));
$bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, 'You said: ' . htmlspecialchars($message->text)));

$bot->poll();
```

```bash
php bot.php
```

### 3b. Webhook

`webhook.php`, served over HTTPS:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),
));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello!'));

$bot->callback('my_button', function (stdClass $callback, Bot $bot) {
    $bot->answer($callback);
    $bot->editMessageText(
        $callback->message->chat->id,
        $callback->message->message_id,
        'Button clicked!'
    );
});

$bot->handle();
```

Register the webhook with the same secret:

```bash
vendor/bin/tgbot webhook:set --url=https://your-domain.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
vendor/bin/tgbot webhook:info
```

## Security

- Keep the token in environment variables, never in the repository
- Always configure a webhook secret token
- Escape user input in HTML messages with `Formatter::escape()`
- Use HTTPS for webhooks

## Troubleshooting

### The bot does not respond

1. `vendor/bin/tgbot bot:info` checks the token
2. `vendor/bin/tgbot webhook:info` shows the last webhook delivery error
3. Long polling fails with `409 Conflict` while a webhook is set. Run `vendor/bin/tgbot webhook:delete` first.
4. Check the PHP error log. Handler exceptions are logged there when no `onError` handler is set.

### Debug mode

```php
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: $token,
    debug: '/var/log/telegram-bot.log', // every request and response; true for the PHP error log
));
```

## Next Steps

- [API Reference](api-reference.md)
- [Advanced Features](advanced.md)
- [Examples](../examples)
- [Security Guide](../SECURITY.md)
