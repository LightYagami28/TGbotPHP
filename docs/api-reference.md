# API reference

## Bot

`TGbotPHP\Framework\Bot` extends `ApiClient` and adds routing, middleware, events and conversations.

```php
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

$bot = new Bot('123456:ABC...');                          // token
$bot = new Bot(new Config('123456:ABC...', timeout: 20));   // or a Config
$bot = new Bot('123456:ABC...', new MyTransport());         // custom HTTP transport
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
$bot->edit($callbackQuery, 'New text', ['reply_markup' => $keyboard]); // message of the button, inline messages too
$bot->chatId($messageOrCallback);  // int|string
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
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;

new Config(
    token: '123456:ABC...',
    secretToken: false,           // webhook secret token (1-256 chars: A-Z a-z 0-9 _ -)
    apiBaseUrl: Config::DEFAULT_API_URL, // or a local Bot API server
    enforceHttps: true,           // set to false only for a local server over plain HTTP
    timeout: 10,                  // seconds; long polling adds its own timeout on top
    retry: new RetryPolicy(maxRetries: 1, maxDelay: 30), // retries after a 429 error
    debug: false,                 // true: PHP error log; a string: log file path
);
```

`Config` is immutable: its properties are read-only.
```

## ApiClient methods

Methods return the decoded `result`: an array for objects, `bool` for actions. A response of the wrong type, or an error, throws an exception.

Methods take the required parameters and the common optional ones as arguments. Every other optional parameter goes in the trailing `array $options`, using the names from the Telegram documentation:

```php
$bot->sendMessage($chatId, 'Hi', options: [
    'message_thread_id' => 5,
    'reply_parameters' => ['message_id' => 42],
    'protect_content' => true,
]);

$bot->sendPoll($chatId, 'Lunch?', ['Pizza', 'Sushi'], type: 'regular', options: ['allows_multiple_answers' => true]);
$bot->sendLocation($chatId, 45.46, 9.19, options: ['live_period' => 600]);
$bot->promoteChatMember($chatId, $userId, ['can_delete_messages' => true, 'can_pin_messages' => true]);
$bot->sendInvoice($chatId, 'Pro plan', 'One month', 'order-42', 'XTR', [['label' => 'Pro', 'amount' => 100]]);
```

A parameter Telegram adds in the future works right away through `$options`.

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

Every method of Bot API 10.3 is implemented. `tests/Unit/BotApiCoverageTest.php` checks each one against the official documentation: method name, required parameters, parameter names and result type.

| Group | Methods |
|---|---|
| Updates | `getUpdates`, `setWebhook`, `deleteWebhook`, `getWebhookInfo` |
| Bot, users & files | `getMe`, `logOut`, `close`, `getUserProfilePhotos`, `getUserChatBoosts`, `getFile`, `getFileUrl`, `downloadFile`, `getUserProfileAudios`, `setPassportDataErrors`, `setUserEmojiStatus` |
| Messages | `sendMessage`, `forwardMessage`, `forwardMessages`, `copyMessage`, `copyMessages`, `sendPhoto`, `sendAudio`, `sendDocument`, `sendVideo`, `editMessageText`, `editMessageCaption`, `editMessageMedia`, `editMessageReplyMarkup`, `deleteMessage`, `deleteMessages`, `sendChatAction`, `approveSuggestedPost`, `declineSuggestedPost`, `editMessageChecklist`, `getUserPersonalChatMessages`, `sendChecklist`, `sendMessageDraft`, `sendRichMessage`, `sendRichMessageDraft` |
| Media | `sendAnimation`, `sendVoice`, `sendVideoNote`, `sendMediaGroup`, `sendLivePhoto`, `sendPaidMedia` |
| Ephemeral messages | `deleteEphemeralMessage`, `editEphemeralMessageCaption`, `editEphemeralMessageMedia`, `editEphemeralMessageReplyMarkup`, `editEphemeralMessageText` |
| Location & polls | `sendLocation`, `editMessageLiveLocation`, `stopMessageLiveLocation`, `sendVenue`, `sendContact`, `sendPoll`, `stopPoll`, `sendDice` |
| Callbacks & inline | `answerCallbackQuery`, `answerInlineQuery`, `answerWebAppQuery`, `answerGuestQuery`, `savePreparedInlineMessage`, `savePreparedKeyboardButton` |
| Chats | `getChat`, `getChatMember`, `getChatAdministrators`, `getChatMemberCount`, `leaveChat`, `setChatTitle`, `setChatDescription`, `setChatPhoto`, `deleteChatPhoto`, `setChatPermissions`, `pinChatMessage`, `unpinChatMessage`, `unpinAllChatMessages`, `exportChatInviteLink`, `createChatInviteLink`, `editChatInviteLink`, `revokeChatInviteLink`, `approveChatJoinRequest`, `declineChatJoinRequest`, `setChatStickerSet`, `deleteChatStickerSet`, `answerChatJoinRequestQuery`, `createChatSubscriptionInviteLink`, `editChatSubscriptionInviteLink`, `sendChatJoinRequestWebApp`, `setChatMemberTag` |
| Administration | `banChatMember`, `unbanChatMember`, `restrictChatMember`, `promoteChatMember`, `setChatAdministratorCustomTitle`, `banChatSenderChat`, `unbanChatSenderChat` |
| Bot profile | `setMyCommands`, `getMyCommands`, `deleteMyCommands`, `setMyName`, `getMyName`, `setMyDescription`, `getMyDescription`, `setMyShortDescription`, `getMyShortDescription`, `setChatMenuButton`, `getChatMenuButton`, `setMyDefaultAdministratorRights`, `getMyDefaultAdministratorRights`, `removeMyProfilePhoto`, `setMyProfilePhoto` |
| Forum topics | `getForumTopicIconStickers`, `createForumTopic`, `editForumTopic`, `closeForumTopic`, `reopenForumTopic`, `deleteForumTopic`, `unpinAllForumTopicMessages`, `editGeneralForumTopic`, `closeGeneralForumTopic`, `reopenGeneralForumTopic`, `hideGeneralForumTopic`, `unhideGeneralForumTopic`, `unpinAllGeneralForumTopicMessages` |
| Stickers | `sendSticker`, `getStickerSet`, `getCustomEmojiStickers`, `uploadStickerFile`, `createNewStickerSet`, `addStickerToSet`, `setStickerPositionInSet`, `deleteStickerFromSet`, `setStickerEmojiList`, `setStickerKeywords`, `setStickerSetTitle`, `deleteStickerSet`, `replaceStickerInSet`, `setCustomEmojiStickerSetThumbnail`, `setStickerMaskPosition`, `setStickerSetThumbnail` |
| Reactions | `setMessageReaction`, `deleteAllMessageReactions`, `deleteMessageReaction` |
| Payments & Stars | `sendInvoice`, `createInvoiceLink`, `answerShippingQuery`, `answerPreCheckoutQuery`, `refundStarPayment`, `getStarTransactions`, `editUserStarSubscription`, `getMyStarBalance` |
| Gifts | `getAvailableGifts`, `getChatGifts`, `getUserGifts`, `giftPremiumSubscription`, `sendGift` |
| Business accounts | `convertGiftToStars`, `deleteBusinessMessages`, `getBusinessAccountGifts`, `getBusinessAccountStarBalance`, `getBusinessConnection`, `readBusinessMessage`, `removeBusinessAccountProfilePhoto`, `setBusinessAccountBio`, `setBusinessAccountGiftSettings`, `setBusinessAccountName`, `setBusinessAccountProfilePhoto`, `setBusinessAccountUsername`, `transferBusinessAccountStars`, `transferGift`, `upgradeGift` |
| Stories | `deleteStory`, `editStory`, `postStory`, `repostStory` |
| Managed bots | `getManagedBotAccessSettings`, `getManagedBotToken`, `replaceManagedBotToken`, `setManagedBotAccessSettings` |
| Verification | `removeChatVerification`, `removeUserVerification`, `verifyChat`, `verifyUser` |
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
MessageParser::entities($message, 'mention');        // ['@alice']: the entities Telegram detected
MessageParser::extractMentions($text); MessageParser::extractHashtags($text);
MessageParser::extractUrls($text); MessageParser::extractEmails($text);
```

`entities()` reads `entities` (or `caption_entities`) and handles their UTF-16 offsets, so emoji before an entity do not shift it. Prefer it to the `extract*()` regexes, which only approximate Telegram's rules.

### Reading payloads

Update payloads are `stdClass` objects decoded from JSON, so every field is `mixed`. `Support\Value` reads them safely:

```php
use TGbotPHP\Support\Value;

$text   = Value::string($message->text ?? null);             // '' if missing or not a string
$userId = Value::id(Value::path($message, 'from', 'id'));   // int|string|null
$page   = Value::int($matches[1] ?? null, default: 1);
$token  = Value::env('TELEGRAM_BOT_TOKEN');                 // null if unset or empty
```

`Support\Payload` reads the fields shared by messages and callback queries: `chatId()`, `userId()`, `topicId()`, `businessConnectionId()`, `directMessagesTopicId()`, and `replyTarget()`, the parameters `reply()` uses to answer in the same place.

### Security

```php
use TGbotPHP\Security\WebhookValidator;

WebhookValidator::validate($secret, WebhookValidator::getSecretToken());
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
