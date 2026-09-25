# Upgrading from 2.x

3.0 fixes bugs that made 2.x unusable in several places (most methods threw a `TypeError`, uploads were broken, commands did not match), so upgrading is strongly recommended. It needs **PHP 8.4**.

## Constructor

```php
// 2.x
$bot = new Bot($token, $debug, $debugFile, $secretToken);

// 3.0
use TGbotPHP\Core\Config;

$bot = new Bot(new Config(
    token: $token,
    secretToken: $secretToken,
    debug: $debugFile,          // true for the PHP error log, a path for a file
));
```

`new Bot($token)` still works when you need no options. `Config` is immutable; `maxRetries`/`maxRetryDelay` became `retry: new RetryPolicy($maxRetries, $maxDelay)`.

## Handlers

Handlers now receive the payload, then the bot, then the route data:

```php
// 3.0
$bot->command('start', function (stdClass $message, Bot $bot, string $args): void {
    $bot->reply($message, 'Hello');
});
```

Handlers written as `function ($message)` keep working. Commands registered as `start` now match `/start` and `/start@your_bot`.

## Files

A string is now always sent as a `file_id` or a URL. Wrap local files:

```php
use TGbotPHP\Types\InputFile;

$bot->sendPhoto($chatId, InputFile::fromPath('/path/photo.jpg'));
```

## Methods

Rarely used optional parameters moved into the trailing `$options` array, with Telegram's parameter names:

| Method | Now in `$options` |
|---|---|
| `sendLocation`, `editMessageLiveLocation` | `horizontal_accuracy`, `live_period`, `heading`, `proximity_alert_radius` |
| `sendVenue` | `foursquare_*`, `google_place_*` |
| `sendPoll` | everything except `type`; the answers argument is named `$answers` |
| `sendAnimation`, `sendVideo` | `duration`, `width`, `height`, `thumbnail`, `supports_streaming` |
| `sendInvoice` | `provider_token`, `max_tip_amount`, `suggested_tip_amounts` |
| `answerInlineQuery` | `button` replaces `switchPmText` and `switchPmParameter` |

Also:

- `promoteChatMember()` takes the rights as an array: `['can_delete_messages' => true]`.
- `createNewStickerSet()` and `addStickerToSet()` take `InputSticker` arrays, as the current API does.
- Methods return `array` or `bool` instead of `array|null`: errors always throw an exception.
- `WebhookValidator::validate($body, $secret, $header)` became `validate($secret, $header)`.
- `getMessageReactions()` was removed: it is not a Bot API method. `ApiClient::getBotToken()` became `getToken()`.
- `kickChatMember()`, `pinMessage()`, `unpinMessage()` and `getChatMembersCount()` still work, as deprecated aliases.

## Errors

A handler exception goes to the `onError()` listeners when there are any. Without listeners, `handle()` and `poll()` log it and continue, and `handleUpdate()` throws it.

## Your own implementations

- A custom `CacheInterface` needs `update()`, atomic for the same key (see [Conversations](Conversations#concurrent-updates)).
- A custom `TransportInterface` needs `download()`.

The complete list is in the [CHANGELOG](https://github.com/LightYagami28/TGbotPHP/blob/main/CHANGELOG.md).
