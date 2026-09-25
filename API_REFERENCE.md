# TGbotPHP API Reference

## Bot

`TGbotPHP\Framework\Bot` extends `ApiClient` and adds routing, middleware, events and conversations.

```php
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot('123456:ABC...');                        // token
$bot = new Bot(new Config('123456:ABC...', timeout: 20)); // or a Config
```

### Handlers

Every handler receives the update payload (`stdClass`) first, then the `Bot`, then data specific to the route.

| Method | Handler signature | Notes |
|---|---|---|
| `command('start', $h)` | `fn(stdClass $message, Bot $bot, string $args)` | `start`, `/start`, `START` are the same. `$args` holds the deep-link payload. |
| `hears($pattern, $h)` | `fn(stdClass $message, Bot $bot, array $matches)` | Checks the text or the caption |
| `callback($pattern, $h)` | `fn(stdClass $callbackQuery, Bot $bot, array $matches)` | Call `$bot->answer($callbackQuery)` |
| `inlineQuery($pattern, $h)` | `fn(stdClass $inlineQuery, Bot $bot, array $matches)` | `'*'` matches everything |
| `state($name, $h)` | `fn(stdClass $message, Bot $bot, array $data)` | Requires `useConversations()` |
| `onUpdate($type, $h)` | `fn(stdClass $payload, Bot $bot, stdClass $update)` | Any update type, e.g. `chat_member`, `pre_checkout_query`. Runs when no route above handled the update |
| `fallback($h)` | `fn(stdClass $message, Bot $bot)` | Text messages no other route handled |
| `onUnknownCommand($h)` | `fn(stdClass $message, Bot $bot, string $args)` | |
| `onError($h)` | `fn(Throwable $e, ?stdClass $update, Bot $bot)` | Without an error handler, exceptions are re-thrown |

A pattern can be:

- exact: `'menu'`
- wildcard: `'page:*'`, where `$matches[1]` is the part `*` matched
- regex: `'/^item:(\d+)$/'` (delimiters `/`, `#` or `~`)

For a message, the router tries commands first, then the conversation state, then `hears` patterns, then `fallback`.

### Running

```php
$bot->handle();                    // webhook: reads php://input, checks the secret token
$bot->handle($body, $secretHeader); // explicit input (frameworks, tests)
$bot->handleUpdate($jsonOrArrayOrObject);
$bot->poll(timeout: 30, allowedUpdates: ['message', 'callback_query']);
$bot->stop();                      // from a handler or a signal handler
```

`handle()` returns `false` and sends 403 when the secret token is wrong, and sends 400 when the JSON is invalid. A handler error still gets a 2xx response, so Telegram does not re-deliver the same update forever. The error goes to `onError` or the PHP error log.

`poll()` calls `getMe()` to learn the bot username, retries network and 5xx errors with exponential backoff, and waits out 429 errors. It stops on 401, 404 and 409 errors, for example when a webhook is still set.

### Helpers

```php
$bot->reply($message, 'text', ['reply_markup' => $keyboard]); // same chat and forum topic, HTML by default
$bot->answer($callbackQuery, 'Saved!', showAlert: false);
$bot->setUsername('my_bot');     // ignore /cmd@other_bot
$bot->useConversations($cache);  // enable state()
$bot->setState($message, 'ask_name', ['step' => 1]);
$bot->updateStateData($message, ['name' => 'Ada']);
$bot->clearState($message);
$bot->plugin(new MyPlugin());
```

### Middleware

```php
// Simple: return false to stop processing
$bot->middleware(fn(stdClass $update, Bot $bot) => !in_array(UpdateParser::getUser($update)?->id, $banned, true));

// Onion: the third parameter is $next
$bot->middleware(function (stdClass $update, Bot $bot, callable $next) {
    $start = microtime(true);
    $next();
    error_log(sprintf('update %d took %.1f ms', $update->update_id, (microtime(true) - $start) * 1000));
});
```

### Events

`update.received`, `update.processed`, `error`, `error.api`, `polling.started`, `polling.stopped`

```php
$bot->on('update.received', fn(stdClass $update) => ...);
```

## Config

```php
new Config(
    token: '123456:ABC...',
    debug: false,                 // log every request and response
    debugFile: false,             // log file (false: PHP error log)
    secretToken: false,           // webhook secret token (1-256 chars: A-Z a-z 0-9 _ -)
    enforceHttps: true,
    apiBaseUrl: 'https://api.telegram.org', // local Bot API server
    timeout: 10,                  // seconds; long polling adds its own timeout on top
    maxRetries: 1,                // retries after a 429 error
    maxRetryDelay: 30,            // never wait longer than this for a retry
);
```

## ApiClient methods

Methods return the decoded `result`: an array for objects, `bool` for actions. Errors throw an exception.

Sending and editing methods accept a trailing `array $options` merged into the request, for example:

```php
$bot->sendMessage($chatId, 'Hi', options: [
    'message_thread_id' => 5,
    'reply_parameters' => ['message_id' => 42],
    'protect_content' => true,
]);
```

Parameter conversion is automatic. `null` values are dropped, booleans become `true`/`false`, and arrays or `JsonSerializable` objects (such as `InlineKeyboard`) are JSON encoded.

### Files

Strings are sent as they are, so a `file_id` or an HTTP URL works. Use `InputFile` to upload:

```php
use TGbotPHP\Types\InputFile;

$bot->sendPhoto($chatId, 'AgACAgIAAxkB...');                    // file_id
$bot->sendPhoto($chatId, 'https://example.com/cat.jpg');        // URL
$bot->sendPhoto($chatId, InputFile::fromPath('/tmp/cat.jpg'));  // upload
$bot->sendDocument($chatId, InputFile::fromContents($csv, 'report.csv', 'text/csv'));

$bot->sendMediaGroup($chatId, [
    ['type' => 'photo', 'media' => InputFile::fromPath('a.jpg')],
    ['type' => 'photo', 'media' => InputFile::fromPath('b.jpg'), 'caption' => 'Two'],
]);

$file = $bot->getFile($fileId);
$bot->downloadFile($fileId, '/tmp/download.jpg');
```

### Method groups

| Group | Methods |
|---|---|
| Updates | `getUpdates`, `setWebhook`, `deleteWebhook`, `getWebhookInfo` |
| Bot & users | `getMe`, `logOut`, `close`, `getUserProfilePhotos`, `getUserChatBoosts`, `getFile`, `getFileUrl`, `downloadFile` |
| Messages | `sendMessage`, `forwardMessage(s)`, `copyMessage(s)`, `sendPhoto`, `sendAudio`, `sendDocument`, `sendVideo`, `editMessageText`, `editMessageCaption`, `editMessageMedia`, `editMessageReplyMarkup`, `deleteMessage(s)`, `sendChatAction` |
| Media | `sendAnimation`, `sendVoice`, `sendVideoNote`, `sendMediaGroup` |
| Location & polls | `sendLocation`, `editMessageLiveLocation`, `stopMessageLiveLocation`, `sendVenue`, `sendContact`, `sendPoll`, `stopPoll`, `sendDice` |
| Callbacks & inline | `answerCallbackQuery`, `answerInlineQuery`, `answerWebAppQuery` |
| Chats | `getChat`, `getChatMember`, `getChatAdministrators`, `getChatMemberCount`, `leaveChat`, `setChatTitle`, `setChatDescription`, `setChatPhoto`, `deleteChatPhoto`, `setChatPermissions`, `pinChatMessage`, `unpinChatMessage`, `unpinAllChatMessages`, `exportChatInviteLink`, `createChatInviteLink`, `editChatInviteLink`, `revokeChatInviteLink`, `approveChatJoinRequest`, `declineChatJoinRequest`, `setChatStickerSet`, `deleteChatStickerSet` |
| Administration | `banChatMember`, `unbanChatMember`, `restrictChatMember`, `promoteChatMember`, `setChatAdministratorCustomTitle`, `banChatSenderChat`, `unbanChatSenderChat` |
| Bot profile | `setMyCommands`, `getMyCommands`, `deleteMyCommands`, `setMyName`, `getMyName`, `setMyDescription`, `getMyDescription`, `setMyShortDescription`, `getMyShortDescription`, `setChatMenuButton`, `getChatMenuButton`, `setMyDefaultAdministratorRights`, `getMyDefaultAdministratorRights` |
| Forum topics | `getForumTopicIconStickers`, `createForumTopic`, `editForumTopic`, `closeForumTopic`, `reopenForumTopic`, `deleteForumTopic`, `unpinAllForumTopicMessages`, `editGeneralForumTopic`, `closeGeneralForumTopic`, `reopenGeneralForumTopic`, `hideGeneralForumTopic`, `unhideGeneralForumTopic`, `unpinAllGeneralForumTopicMessages` |
| Stickers | `sendSticker`, `getStickerSet`, `getCustomEmojiStickers`, `uploadStickerFile`, `createNewStickerSet`, `addStickerToSet`, `setStickerPositionInSet`, `deleteStickerFromSet`, `setStickerEmojiList`, `setStickerKeywords`, `setStickerSetTitle`, `deleteStickerSet` |
| Reactions | `setMessageReaction` (accepts plain emoji: `['👍']`) |
| Payments | `sendInvoice`, `createInvoiceLink`, `answerShippingQuery`, `answerPreCheckoutQuery`, `refundStarPayment`, `getStarTransactions` |
| Games | `sendGame`, `setGameScore`, `getGameHighScores` |
| Anything else | `call('methodName', [...])` |

Deprecated aliases still work: `kickChatMember` calls `banChatMember`, `pinMessage` calls `pinChatMessage`, `unpinMessage` calls `unpinChatMessage`, and `getChatMembersCount` calls `getChatMemberCount`.

`setMyCommands` accepts `['start' => 'Start the bot']`, and a scope can be given by its type (`'all_private_chats'`).

## Exceptions

| Exception | When |
|---|---|
| `InvalidTokenException` | Malformed token or configuration passed to `Bot` |
| `ApiException` | Telegram returned `ok: false`. See `getCode()`, `getApiMethod()`, `getApiResponse()`, `getMigrateToChatId()` |
| `TooManyRequestsException` | 429 flood control after the retries ran out. See `getRetryAfter()` |
| `NetworkException` | Transport failure (DNS, TLS, timeout) |

All of them extend `TelegramException`.

## Utilities

### Keyboards

```php
use TGbotPHP\Utilities\Keyboard;
use TGbotPHP\Utilities\InlineKeyboard;

Keyboard::inline(['Yes' => 'yes', 'No' => 'no']);          // one row
Keyboard::grid($buttons, cols: 3);
Keyboard::links(['Docs' => 'https://core.telegram.org']);
Keyboard::pagination(currentPage: 2, totalPages: 10, callbackPrefix: 'page:');
Keyboard::reply([['Yes', 'No'], [['text' => 'Send location', 'request_location' => true]]], oneTime: true);
Keyboard::remove();
Keyboard::forceReply('Your name');

InlineKeyboard::make()
    ->button('Buy', 'buy:42')->url('Site', 'https://example.com')
    ->row()
    ->webApp('Open app', 'https://example.com/app')
    ->switchInline('Share', 'query');
```

### Formatting (HTML parse mode)

```php
use TGbotPHP\Utilities\Formatter;

Formatter::escape($userInput);
Formatter::bold('x'); Formatter::italic('x'); Formatter::underline('x'); Formatter::strike('x');
Formatter::spoiler('x'); Formatter::code('x'); Formatter::pre($code, 'php'); Formatter::quote('x', expandable: true);
Formatter::link('text', 'https://...'); Formatter::mention($userId, $name);
Formatter::escapeMarkdownV2($text);
```

### Parsing

```php
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Utilities\MessageParser;

UpdateParser::getType($update);    // "message", "callback_query", ...
UpdateParser::getChat($update);    // chat of any update, or null
UpdateParser::getUser($update);    // user who triggered the update, or null

MessageParser::parseCommand('/start@bot payload'); // ['command' => 'start', 'args' => 'payload', 'username' => 'bot']
MessageParser::parseArguments('add "buy milk" 2'); // ['add', 'buy milk', '2']
MessageParser::extractMentions($text); MessageParser::extractHashtags($text);
MessageParser::extractUrls($text); MessageParser::extractEmails($text);
```

### Security

```php
use TGbotPHP\Security\WebhookValidator;

WebhookValidator::validate($body, $secret, WebhookValidator::getSecretToken());
WebhookValidator::isTelegramIp($_SERVER['REMOTE_ADDR']);
$data = WebhookValidator::validateWebAppData($initData, $token, maxAge: 3600); // null if invalid
```

## CLI

```bash
export TELEGRAM_BOT_TOKEN=123456:ABC...
vendor/bin/tgbot bot:info
vendor/bin/tgbot webhook:set --url=https://example.com/hook.php --secret="$TELEGRAM_SECRET_TOKEN" --drop-pending
vendor/bin/tgbot webhook:info
vendor/bin/tgbot webhook:delete
vendor/bin/tgbot commands:list --scope=all_private_chats
vendor/bin/tgbot commands:delete
```
