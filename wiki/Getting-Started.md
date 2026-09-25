# Getting Started

## 1. Create a bot

1. Open [@BotFather](https://t.me/botfather) in Telegram and send `/newbot`.
2. Choose a name and a username ending in `bot`.
3. Copy the token (`123456789:AA...`). Anyone with the token controls the bot: keep it out of your code and your repository.

## 2. Install

```bash
composer require lightyagami28/tgbotphp
```

You need PHP 8.4 or later with the `curl` and `json` extensions.

## 3. Your first bot: long polling

Long polling asks Telegram for new updates in a loop. It works anywhere, even on your laptop, without a public URL.

`bot.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Framework\Bot;
use TGbotPHP\Utilities\Formatter;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

$bot->command('start', function (stdClass $message, Bot $bot): void {
    $name = Formatter::escape($message->from->first_name);
    $bot->reply($message, "Hello <b>$name</b>! Send me anything and I will repeat it.");
});

$bot->fallback(function (stdClass $message, Bot $bot): void {
    $bot->reply($message, Formatter::escape($message->text));
});

$bot->poll();
```

```bash
export TELEGRAM_BOT_TOKEN=123456789:AA...
php bot.php
```

Open your bot in Telegram and send `/start`.

Things to notice:

- Handlers receive the update payload (here the `Message`, as a `stdClass` decoded from JSON), then the bot, then data from the route.
- `reply()` answers in the same chat, and in the same forum topic, business connection or direct messages topic.
- Messages use HTML formatting by default. Escape what users write with `Formatter::escape()`.
- Long polling does not work while a webhook is set. Remove it with `vendor/bin/tgbot webhook:delete`.

## 4. The same bot behind a webhook

With a webhook, Telegram sends each update to your HTTPS URL. Every update is a new PHP request, so it scales like any web page.

`public/webhook.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot(new Config(
    token: getenv('TELEGRAM_BOT_TOKEN'),
    secretToken: getenv('TELEGRAM_SECRET_TOKEN'),
));

$bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hello!'));

$bot->handle();
```

Generate a secret and register the webhook:

```bash
export TELEGRAM_SECRET_TOKEN=$(openssl rand -hex 32)
vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
vendor/bin/tgbot webhook:info
```

`handle()` answers 403 to requests without the right secret, so only Telegram can call your bot. See [Deployment](Deployment) for the server configuration.

## Next steps

- [Handling Updates](Handling-Updates): commands with arguments, text patterns, buttons
- [Keyboards and Callbacks](Keyboards-and-Callbacks): menus and pagination
- [Conversations](Conversations): ask several questions in a row
- [Examples](Examples): complete bots
