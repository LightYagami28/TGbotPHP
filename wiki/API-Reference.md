# API Reference

`Bot` extends `ApiClient`, which has a method for every method of the [Bot API](https://core.telegram.org/bots/api) (version 10.3). Use `ApiClient` alone when you only need to send messages, without handlers:

```php
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;

$api = new ApiClient(new Config(getenv('TELEGRAM_BOT_TOKEN')));
$api->sendMessage(123456789, 'Backup finished');
```

## Calling methods

Method names are the ones of the Bot API. Required parameters are arguments, and so are the optional ones most calls use:

```php
$sent = $bot->sendMessage($chatId, '<b>Hello</b>');
$bot->sendPhoto($chatId, 'https://example.com/cat.jpg', caption: 'A cat');
$bot->banChatMember($chatId, $userId, untilDate: time() + 3600);
$bot->pinChatMessage($chatId, $sent['message_id'], disableNotification: true);
```

Every other optional parameter goes in the trailing `$options` array, with the names of the Telegram documentation:

```php
$bot->sendMessage($chatId, 'In a topic, as a reply', options: [
    'message_thread_id' => 5,
    'reply_parameters' => ['message_id' => 42],
    'protect_content' => true,
]);

$bot->sendPoll($chatId, 'Lunch?', ['Pizza', 'Sushi'], options: ['allows_multiple_answers' => true]);
$bot->sendLocation($chatId, 45.4642, 9.1900, options: ['live_period' => 600]);
$bot->promoteChatMember($chatId, $userId, ['can_delete_messages' => true, 'can_pin_messages' => true]);
```

A parameter Telegram adds in a future version works right away through `$options`. A method Telegram adds later works through `call()`:

```php
$result = $bot->call('someNewMethod', ['chat_id' => $chatId]);
```

### Parameters

The library encodes parameters as Telegram expects:

- `null` values are not sent;
- booleans become `true` or `false`;
- dates (`DateTimeInterface`) become Unix timestamps, so `'until_date' => new DateTimeImmutable('+1 day')` works in `$options`, and backed enums become their value;
- arrays and `JsonSerializable` objects (such as `InlineKeyboard`) are JSON encoded;
- `InputFile` objects are uploaded (see [Files](#files)).

### Results

Methods return the `result` of Telegram's response, as PHP arrays: `$sent['message_id']`, `$me['username']`. Methods documented as returning *True* return `bool`; `getChatMemberCount()` returns `int`; `exportChatInviteLink()` returns `string`. Edit methods return the edited message, or `true` for messages sent in inline mode.

The library checks the type of each result: an unexpected answer throws an `ApiException` instead of surfacing later as a `TypeError`.

### Formatting

`sendMessage()`, `editMessageText()` and the media methods use `parse_mode: 'HTML'` by default. Pass `parseMode: null` to send plain text, or `'MarkdownV2'`. Escape user input with `Formatter::escape()`; see [Keyboards and Callbacks](Keyboards-and-Callbacks#formatting).

## Files

A string is sent as it is, so it can be a `file_id` (a file already on Telegram's servers) or an HTTP URL. Wrap files to upload in `InputFile`:

```php
use TGbotPHP\Types\InputFile;

$bot->sendPhoto($chatId, 'AgACAgIAAxkBAAIB...');                  // file_id: no upload
$bot->sendPhoto($chatId, 'https://example.com/cat.jpg');          // Telegram downloads it
$bot->sendPhoto($chatId, InputFile::fromPath('/srv/cat.jpg'));    // upload from disk
$bot->sendDocument($chatId, InputFile::fromContents($csv, 'report.csv', 'text/csv'));
```

The library never reads a local file from a plain string, so a path coming from user input cannot leak files from your server.

Albums and other methods with nested files work the same way; the library adds the `attach://` references:

```php
$bot->sendMediaGroup($chatId, [
    ['type' => 'photo', 'media' => InputFile::fromPath('a.jpg'), 'caption' => 'Two photos'],
    ['type' => 'photo', 'media' => InputFile::fromPath('b.jpg')],
]);
```

After an upload, reuse the `file_id` from the result instead of uploading again:

```php
$sent = $bot->sendDocument($chatId, InputFile::fromPath('manual.pdf'));
$fileId = $sent['document']['file_id'];
```

### Downloads

```php
$contents = $bot->downloadFile($fileId);                     // in memory, up to 20 MB
$bot->downloadFile($fileId, '/srv/uploads/voice.ogg');       // streamed to disk
```

- With a destination, the file is streamed to disk and only appears there once complete.
- Without a destination, files over 20 MB are refused instead of filling the memory.
- With a [local Bot API server](Configuration#local-bot-api-server), files are copied from its disk.
- `getFileUrl($filePath)` builds the download URL. It contains the bot token: never show it to users.

## Errors

Every failure throws an exception extending `TGbotPHP\Exceptions\TelegramException`:

| Exception | When |
|---|---|
| `ApiException` | Telegram answered with an error, or an unexpected result |
| `TooManyRequestsException` | Flood control (429), after the retries allowed by the [retry policy](Configuration#retries) |
| `NetworkException` | Telegram could not be reached: DNS, timeout, TLS... |
| `InvalidTokenException` | The token is malformed |
| `StorageException` | A cache or download file could not be written |
| `PluginException` | A plugin was registered twice |

```php
use TGbotPHP\Exceptions\ApiException;

try {
    $bot->sendMessage($chatId, 'Hi');
} catch (ApiException $e) {
    $e->getCode();              // Telegram's error code: 400, 403, 429...
    $e->getMessage();           // Telegram's description: "Forbidden: bot was blocked by the user"
    $e->getApiMethod();         // "sendMessage"
    $e->getMigrateToChatId();   // new id when a group became a supergroup
}
```

Common codes:

- **400** Bad Request: a wrong parameter. `isMessageNotModified()` tells an edit that changed nothing from other errors.
- **403** Forbidden: the user blocked the bot, or the bot is not in the chat.
- **429** Too Many Requests: slow down. `TooManyRequestsException::getRetryAfter()` says for how long.

Errors thrown in handlers go to [`onError()`](Middleware-and-Events#errors).

## Methods

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

The deprecated names of 2.x still work: `kickChatMember()` calls `banChatMember()`, `pinMessage()` calls `pinChatMessage()`, `unpinMessage()` calls `unpinChatMessage()`, and `getChatMembersCount()` calls `getChatMemberCount()`.

A few methods accept shortcuts:

- `setMyCommands(['start' => 'Start the bot'])` builds the `BotCommand` list; a scope can be given by its type: `scope: 'all_private_chats'`.
- `setMessageReaction($chatId, $messageId, ['👍'])` accepts plain emoji.
- `editMessageReplyMarkup($chatId, $messageId)` without a markup removes the inline keyboard.

For the parameters of each method, see the [official documentation](https://core.telegram.org/bots/api#available-methods) or the PHPDoc of the method in your IDE.
