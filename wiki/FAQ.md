# FAQ

**Which PHP version do I need?**
PHP 8.4 or later, with the `curl` and `json` extensions. The library is tested on 8.4, 8.5 and the development version of 8.6.

**Does it have dependencies?**
No runtime dependencies. PHPUnit, PHPStan and PHP-CS-Fixer are only needed to work on the library itself.

**Webhook or long polling?**
Long polling is the simplest: it runs anywhere, even on your laptop. Webhooks need a public HTTPS URL but fit any PHP hosting and scale like web pages. See [Deployment](Deployment).

**Which Bot API version is supported?**
All of Bot API 10.3. A weekly check compares the library with the official documentation. A method or parameter Telegram adds before the library does is usable at once through `call()` and `$options`.

**Why are updates `stdClass` objects and not classes?**
So that every field Telegram adds is available the day it is released, without waiting for a new version of the library. Use `Support\Value` to read optional fields with the right type (see [Handling Updates](Handling-Updates#reading-payloads)).

**Why do API methods return arrays?**
Results are decoded as associative arrays: `$sent['message_id']`. The library checks that each result has the documented type (object, list, boolean, integer or string).

**How do I send a message without a user writing first?**
A user must have started the bot (or the bot must be in the group) before it can write to them. Then use the chat id you stored: `$bot->sendMessage($chatId, 'Your order has shipped')`.

**How do I send HTML?**
It is the default parse mode. Use `Formatter` to build tags and escape user text (see [Keyboards and Callbacks](Keyboards-and-Callbacks#formatting)).

**How do I send a local file?**
`InputFile::fromPath('/path/file.pdf')`. A plain string is sent as a `file_id` or URL (see [API Reference](API-Reference#files)).

**Can I use it with Laravel or Symfony?**
Yes. Create the `Bot` in a service provider or a service definition, and pass the request to `handle()` from a controller, so the secret token is checked:

```php
$bot->handle($request->getContent(), $request->headers->get('X-Telegram-Bot-Api-Secret-Token'));
```

There is no framework-specific package.

**Can I use Guzzle or another HTTP client?**
Implement `Http\TransportInterface` (three methods) with your client and pass it to the bot (see [Configuration](Configuration#http-transport)).

**How do I test my bot?**
With `BotTester`, without network access (see [Testing](Testing)).

**How do I run several bots?**
Create one `Bot` per token. With webhooks, give each bot its own URL and secret. With long polling, run one process per bot.

**Is it free?**
Yes, under the [Apache License 2.0](https://github.com/LightYagami28/TGbotPHP/blob/main/LICENSE).
