# Keyboards and Callbacks

Telegram has two kinds of keyboards:

- **Inline keyboards** are attached to a message. A button sends a callback query to the bot, opens a URL or a Mini App, or starts an inline query.
- **Reply keyboards** replace the user's keyboard with buttons that send their text as a normal message.

Pass either as `reply_markup`:

```php
$bot->sendMessage($chatId, 'Choose:', replyMarkup: $keyboard);
$bot->reply($message, 'Choose:', ['reply_markup' => $keyboard]);
```

## Inline keyboards

### Builder

`InlineKeyboard` builds keyboards row by row:

```php
use TGbotPHP\Utilities\InlineKeyboard;

$keyboard = InlineKeyboard::make()
    ->button('✅ Accept', 'order:accept:42')
    ->button('❌ Refuse', 'order:refuse:42')
    ->row()
    ->url('Open the shop', 'https://example.com/shop')
    ->row()
    ->webApp('Pay in the app', 'https://example.com/app')
    ->switchInline('Share', 'order 42');
```

| Method | Button |
|---|---|
| `button($text, $callbackData)` | Sends a callback query with `$callbackData` (at most 64 bytes) |
| `url($text, $url)` | Opens a URL |
| `webApp($text, $url)` | Opens a Mini App |
| `switchInline($text, $query, $currentChat)` | Starts an inline query, in another chat or in the current one |
| `copyText($text, $copy)` | Copies `$copy` to the clipboard |
| `pay($text)` | Pay button, for invoices only; it must be the first button |
| `raw($button)` | Any other [InlineKeyboardButton](https://core.telegram.org/bots/api#inlinekeyboardbutton) |
| `row()` | Starts a new row |

### Shortcuts

```php
use TGbotPHP\Utilities\Keyboard;

Keyboard::inline(['Yes' => 'vote:yes', 'No' => 'vote:no']);        // one row
Keyboard::grid(['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], 2); // 2 per row
Keyboard::links(['Docs' => 'https://core.telegram.org/bots/api']);  // URL buttons
Keyboard::pagination(3, 10, 'page:');                               // « 2 | 3 / 10 | 4 »
```

Numeric callback data such as `'123'` is kept as a string: PHP would otherwise turn it into an integer array key.

## Handling button presses

```php
use TGbotPHP\Support\Value;

$bot->command('list', function (stdClass $message, Bot $bot): void {
    $bot->reply($message, 'Page 1', ['reply_markup' => Keyboard::pagination(1, 10)]);
});

$bot->callback('page:*', function (stdClass $callback, Bot $bot, array $matches): void {
    $page = Value::int($matches[1], 1);

    $bot->answer($callback);
    $bot->edit($callback, "Page $page", ['reply_markup' => Keyboard::pagination($page, 10)]);
});
```

- `callback()` takes an exact value (`'menu'`), a wildcard (`'page:*'`) or a regular expression (`'/^order:(accept|refuse):(\d+)$/'`). The captured parts are in `$matches`.
- `answer($callback, $text, $showAlert)` stops the loading indicator on the button, optionally with a notification or an alert. Call it for every callback query.
- `edit($callback, $text, $options)` edits the message the button belongs to. Pressing the button of the page already shown returns `false` instead of throwing Telegram's "message is not modified" error.
- To change only the buttons, use `$bot->editMessageReplyMarkup($bot->chatId($callback), $callback->message->message_id, $keyboard)`.

Callback data is sent by the client and can be forged: check that the user may perform the action before doing it.

## Reply keyboards

```php
$keyboard = Keyboard::reply([
    ['📦 My orders', '🛒 Shop'],
    [['text' => '📍 Send my location', 'request_location' => true]],
], oneTime: true, placeholder: 'Choose an option');

$bot->reply($message, 'Main menu', ['reply_markup' => $keyboard]);

$bot->hears('📦 My orders', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'You have no orders.'));
```

- Rows contain texts, or [KeyboardButton](https://core.telegram.org/bots/api#keyboardbutton) arrays for buttons that request a contact, a location, a poll, a user or a chat.
- `Keyboard::remove()` removes the keyboard; `Keyboard::forceReply('Your name')` asks the user to reply to the message.

## Formatting

Messages are sent with `parse_mode: 'HTML'` by default. `Formatter` builds the tags and escapes the text inside them:

```php
use TGbotPHP\Utilities\Formatter;

$text = Formatter::bold('Order #42') . "\n"
    . 'Customer: ' . Formatter::mention($userId, $name) . "\n"
    . 'Notes: ' . Formatter::escape($notes) . "\n"
    . Formatter::link('Track the parcel', $trackingUrl) . "\n"
    . Formatter::pre($json, 'json');
```

| Method | Result |
|---|---|
| `escape($text)` | Text safe to put in an HTML message |
| `bold()`, `italic()`, `underline()`, `strike()`, `spoiler()`, `code()` | The text in the matching tag, escaped |
| `pre($text, $language)` | A code block, with syntax highlighting when a language is given |
| `quote($text, $expandable)` | A block quote, optionally collapsed |
| `link($text, $url)` | A link |
| `mention($userId, $name)` | A mention that works for users without a username |
| `escapeMarkdownV2($text)` | Text safe for `parseMode: 'MarkdownV2'` |

Always escape what users write: an unescaped `<` makes Telegram reject the whole message.
