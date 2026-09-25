# Handling Updates

Telegram delivers everything that happens to your bot as an [update](https://core.telegram.org/bots/api#update): a message, a button press, a user joining a group, a payment... You register a handler for the updates you care about, and the bot calls it.

## Handler arguments

Every handler receives the update payload, the bot, then data from the route:

| Registered with | Handler signature |
|---|---|
| `command()` | `fn(stdClass $message, Bot $bot, string $args)` |
| `hears()` | `fn(stdClass $message, Bot $bot, array $matches)` |
| `callback()` | `fn(stdClass $callbackQuery, Bot $bot, array $matches)` |
| `inlineQuery()` | `fn(stdClass $inlineQuery, Bot $bot, array $matches)` |
| `state()` | `fn(stdClass $message, Bot $bot, array $stateData)` |
| `onUpdate()` | `fn(stdClass $payload, Bot $bot, stdClass $update)` |
| `fallback()` | `fn(stdClass $message, Bot $bot)` |

Payloads are `stdClass` objects decoded from Telegram's JSON, with the field names of the Bot API (`$message->chat->id`, `$message->from->first_name`). Every field Telegram adds is available immediately. To read optional fields safely, see [Reading payloads](#reading-payloads).

You can declare fewer parameters than the handler receives: `fn(stdClass $message)` works too.

## Commands

```php
$bot->command('start', function (stdClass $message, Bot $bot, string $args): void {
    $bot->reply($message, $args === '' ? 'Welcome!' : "Welcome, you came from $args");
});
```

- `start`, `/start` and `START` register the same command.
- `/start@your_bot` is handled; `/start@other_bot` is ignored. With long polling the bot learns its username by itself. With webhooks, call `$bot->setUsername('your_bot')`.
- `$args` is the text after the command: `/remind 10m tea` gives `10m tea`. For deep links (`t.me/your_bot?start=promo`), it is the start payload (`promo`).
- `MessageParser::parseArguments($args)` splits arguments, keeping quoted values together.

Commands nobody registered go to `onUnknownCommand()`:

```php
$bot->onUnknownCommand(fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Unknown command. Try /help'));
```

Publish your commands so Telegram shows them in the menu:

```php
$bot->setMyCommands(['start' => 'Start the bot', 'help' => 'How to use the bot']);
```

## Text patterns

`hears()` matches the text of a message, or the caption of a photo or video.

```php
use TGbotPHP\Support\Value;

// Exact text
$bot->hears('ping', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'pong'));

// Wildcard: * captures the rest
$bot->hears('weather in *', function (stdClass $message, Bot $bot, array $matches): void {
    $bot->reply($message, 'Looking up the weather in ' . Formatter::escape($matches[1]));
});

// Regular expression, delimited by / # or ~
$bot->hears('/^order #(\d+)$/i', function (stdClass $message, Bot $bot, array $matches): void {
    $bot->reply($message, 'Order ' . Value::int($matches[1]));
});
```

`$matches[0]` is the whole text; the following entries are the captured groups.

## Buttons (callback queries)

A press on an inline keyboard button sends a callback query with the button's `callback_data`. The patterns are the same as for `hears()`:

```php
use TGbotPHP\Utilities\InlineKeyboard;

$bot->command('vote', function (stdClass $message, Bot $bot): void {
    $bot->reply($message, 'Do you like PHP?', [
        'reply_markup' => InlineKeyboard::make()->button('Yes', 'vote:yes')->button('No', 'vote:no'),
    ]);
});

$bot->callback('vote:*', function (stdClass $callback, Bot $bot, array $matches): void {
    $bot->answer($callback, "You voted {$matches[1]}");
    $bot->edit($callback, 'Thanks for voting!');
});
```

- Always call `answer()`: until you do, the button shows a loading indicator.
- `answer($callback, $text, showAlert: true)` shows a dialog instead of a short notification.
- `edit()` changes the message the button belongs to, including messages sent in inline mode. It returns `false` when the text did not change, instead of failing.

See [Keyboards and Callbacks](Keyboards-and-Callbacks) for building keyboards.

## Inline queries

When users type `@your_bot something` in any chat, the bot receives an inline query. Enable inline mode with `/setinline` in @BotFather first.

```php
$bot->inlineQuery('*', function (stdClass $query, Bot $bot, array $matches): void {
    $bot->answerInlineQuery($query->id, [[
        'type' => 'article',
        'id' => '1',
        'title' => 'Shout it',
        'input_message_content' => ['message_text' => strtoupper($matches[1])],
    ]], cacheTime: 0);
});
```

## Other update types

`onUpdate()` handles any update type by its name in the [Update](https://core.telegram.org/bots/api#update) object: `edited_message`, `channel_post`, `chat_member`, `my_chat_member`, `chat_join_request`, `message_reaction`, `business_message`, `pre_checkout_query`, `poll_answer`, `managed_bot`, `subscription`...

```php
$bot->onUpdate('chat_member', function (stdClass $member, Bot $bot): void {
    if ($member->new_chat_member->status === 'member') {
        $bot->sendMessage($member->chat->id, 'Welcome ' . Formatter::escape($member->new_chat_member->user->first_name));
    }
});

$bot->onUpdate('pre_checkout_query', fn(stdClass $query, Bot $bot) => $bot->answerPreCheckoutQuery($query->id, true));
```

`onUpdate('message', ...)` receives messages that no other route matched, such as photos, stickers or locations:

```php
$bot->onUpdate('message', function (stdClass $message, Bot $bot): void {
    if (isset($message->photo)) {
        $bot->reply($message, 'Nice photo!');
    }
});
```

Group chats, channel posts and some updates are only delivered if you ask for them. With long polling pass `allowedUpdates` to `poll()`; with webhooks pass it to `setWebhook()`. By default Telegram sends every type except `chat_member`, `message_reaction` and `message_reaction_count`.

## Fallback

`fallback()` receives text messages that matched nothing else:

```php
$bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, "Sorry, I don't understand. Try /help"));
```

## Routing order

For a message:

1. a command (`command()`, then `onUnknownCommand()`);
2. the handler of the user's conversation state, when [conversations](Conversations) are enabled;
3. text patterns (`hears()`): exact texts first, then wildcard and regex patterns in the order they were registered;
4. `fallback()`, for text messages;
5. `onUpdate('message')`.

Callback queries and inline queries try exact patterns first, then wildcard and regex patterns in registration order. Every update type runs its `onUpdate()` handlers when no other route matched.

Before routing, updates go through [middleware](Middleware-and-Events), which can stop them.

## Reading payloads

Fields that Telegram marks as optional may be missing. `Support\Value` reads them without warnings and with the type you expect:

```php
use TGbotPHP\Support\Value;

$text     = Value::string($message->text ?? null);            // '' when missing
$username = Value::nullableString(Value::path($message, 'from', 'username'));
$replyTo  = Value::nullableInt(Value::path($message, 'reply_to_message', 'message_id'));
```

`Support\Payload` reads the fields shared by messages and callback queries: `chatId()`, `userId()`, `topicId()`, `businessConnectionId()`. `$bot->chatId($payload)` is a shortcut.

`MessageParser::entities($message, 'mention')` returns the entities Telegram detected (mentions, hashtags, URLs...), with Telegram's UTF-16 offsets handled correctly.

## Receiving updates

| | Long polling | Webhook |
|---|---|---|
| Start | `$bot->poll()` | `$bot->handle()` in your web entry point |
| Needs a public HTTPS URL | No | Yes |
| Process | One long-running PHP process | One PHP request per update |
| Conversations | `ArrayCache` is enough | Use a persistent cache (`FileCache`, Redis...) |
| Good for | Development, small bots, servers without a web stack | Shared hosting, serverless, high traffic |

`poll()` handles network errors and rate limits by itself, and stops with `$bot->stop()` (from a handler or a signal handler). Details in [Deployment](Deployment).

You can also process an update yourself, for example from a queue: `$bot->handleUpdate($json)` accepts JSON, an array or a decoded object.
