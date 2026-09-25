# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-09-25

### Fixed
- Methods returning `True` or an integer (`deleteMessage`, `banChatMember`, `getChatMemberCount`...) threw a `TypeError` under `strict_types`.
- File uploads were sent with a wrong `Content-Type`, and every string was treated as a local file, so file_ids and URLs could not be sent.
- Commands registered as `start` never matched `/start`; `/cmd@botname` was not handled.
- Telegram error descriptions were replaced by a generic "HTTP 400" message.
- `answerCallbackQuery` was missing, so buttons kept loading on the client.
- Numeric callback data, commands and keyboard labels (`'123'`) broke routing, because PHP turns such array keys into integers.
- `Formatter::escape()` produced `&apos;`, which Telegram rejects.
- The token and secret token checks accepted a trailing newline.
- `null` parameters were sent as empty strings.
- Arrays with gaps in their keys (after `array_filter()` or `unset()`) were sent as JSON objects, so Telegram rejected keyboards, poll options or price lists built that way.
- Long polling delivered updates re-encoded from arrays, so empty JSON objects such as `callback_game` became arrays, unlike with webhooks.
- `setMyCommands()` sent a numeric command name (`'2024' => '...'`) as an invalid object, and uppercase names, which Telegram rejects.
- `Value::int()` turned numbers too large for an integer into `PHP_INT_MAX`, and dropped fractions.
- `RateLimiter` used a window that slid forward on every hit.
- `ArrayCache::has()` returned `false` for stored `null` values.
- Conversation data, session data and rate limit counters could lose updates when two webhook requests from the same user ran at the same time.
- `downloadFile()` loaded whole files in memory, and could not download files from a local Bot API server.
- The CLI used a wrong autoloader path when installed as a dependency, and always exited with status 0.
- `composer.json` and the README declared the MIT license, but `LICENSE` (inherited from the Apache-licensed upstream repository) is the Apache License 2.0. The metadata now says Apache-2.0.

### Added
- `Bot::handle()` for webhooks (secret token check, 403/400 responses, `respondFirst` to answer Telegram before slow handlers run) and `Bot::poll()` for long polling (backoff, 429 handling, `stop()`).
- Routing: `hears()`, `inlineQuery()`, `onUpdate()` for any update type, `fallback()`, `onUnknownCommand()`, and exact, `prefix:*` or regex patterns for callbacks and inline queries. Handlers receive the `Bot` and the route data.
- Middleware can stop processing (`return false`) or wrap it (`$next`).
- Conversations: `useConversations()`, `state()`, `setState()`, `updateStateData()`, `clearState()`.
- `Bot::reply()` answers in the same forum topic, business connection or channel direct messages topic, `edit()` (returns `false` when the content did not change), `answer()`, `chatId()`, `onError()`, and plugins with `BotPluginInterface::boot()`.
- `InputFile` for uploads, with automatic `attach://` references for albums and sticker sets. `downloadFile()` and `getFileUrl()`.
- Every method of Bot API 10.3: business accounts, gifts, stories, checklists, rich messages and drafts, ephemeral messages, paid media, live photos, suggested posts, managed bots, verification and more, including `answerCallbackQuery`, `editMessageCaption`, `editMessageMedia`, `deleteMessages`, `copyMessages`, invite links, join requests, bot profile methods, Telegram Stars payments and the current sticker set methods.
- API results are checked against the declared return type; a mismatch throws `ApiException`.
- `TooManyRequestsException`, `NetworkException`, `StorageException` and `PluginException`. `ApiException` gains `getApiMethod()`, `getParameters()`, `getMigrateToChatId()` and `isMessageNotModified()`.
- `Http\TransportInterface`, to use another HTTP client or a fake one in tests. `Testing\BotTester`, `FakeUpdate` and `FakeTransport` test bots without network access. Support for a local Bot API server.
- `FileCache`, a persistent cache for webhooks that never unserializes objects. `RateLimiter::middleware()`.
- `WebhookValidator::isTelegramIp()`, `validateWebAppData()` (Mini Apps) and `validateWebAppSignature()`, which checks Telegram's Ed25519 signature without the bot token.
- `Formatter` (HTML and MarkdownV2 escaping), the `InlineKeyboard` builder, `Keyboard::reply()`, `remove()`, `forceReply()` and `pagination()`.
- `Support\Value` and `Support\Payload` to read update payloads with types.
- `MessageParser::entities()`, the text of the entities Telegram detected, with UTF-16 offsets handled.
- CLI commands `commands:list` and `commands:delete`, `webhook:set --secret --drop-pending`, and the `TELEGRAM_BOT_TOKEN` environment variable.
- `tools/bot-api.json`, the methods and parameters of the Bot API extracted from the official documentation by `tools/bot-api-spec.php`. A test checks every method against it, and a weekly workflow reports new API versions.
- Parameters accept dates (`DateTimeInterface`, sent as Unix timestamps: `until_date`, `expire_date`...) and backed enums.
- `fetchUpdates()`: `getUpdates` with the updates decoded as objects, like webhook updates. `poll()` uses it.
- `CacheInterface::update()`, an atomic read-change-write; `FileCache` locks the entry meanwhile.
- The update types of Bot API 10.3: `guest_message`, `managed_bot`, `subscription` and `stopped_message_generation`.
- `BotBuilder::withRetry()`.
- A user guide in the wiki, rewritten for 3.0 and kept in `wiki/`, where a test checks its examples against the code. A workflow publishes it.
- Unit tests (97% of lines, at least 95% required by CI), an end-to-end suite against the real API (`--testsuite e2e`), PHPStan at level 10 with strict rules, and runnable examples.
- GitHub workflows: tests with coverage, PHPStan, code style (PHP-CS-Fixer, PER-CS 2.0), workflow linting (actionlint, zizmor), documentation link check, `composer audit`, Docker image build and smoke test, on-demand end-to-end tests, and releases from `v*` tags with notes from this file.

### Changed
- Requires PHP 8.4. CI runs on PHP 8.4 and 8.5, and on 8.6 (in development) without blocking.
- `Bot` is split into `Kernel`, `Router`, `Runner\WebhookHandler`, `Runner\LongPolling` and three traits; its public methods are unchanged.
- Rarely used optional parameters moved into the `$options` array (see the migration notes).
- `editMessageReplyMarkup()` without a markup removes the inline keyboard.
- `downloadFile()` streams to the destination file, which only appears once complete. Without a destination it refuses files over 20 MB.
- The CLI writes errors to stderr, and finds the autoloader through Composer's `$_composer_autoload_path`.
- Middleware signatures are inspected once, when added, instead of on every update.
- `MessageParser::parseArguments()` only unescapes `\"` and `\\` inside quotes.
- `Logger` writes to stderr by default instead of `/tmp/bot.log`.
- `CurlTransport` keeps its connection open between requests. `TransportInterface` gains `download()`.
- The debug log escapes line breaks and redacts `secret_token` and `provider_token`.
- cURL does not follow redirects and only allows HTTP and HTTPS.
- GitHub Actions are pinned to commit SHAs and run with read-only permissions. The Docker image runs as an unprivileged user and only contains the files the bot needs.

### Deprecated
- `kickChatMember`, `pinMessage`, `unpinMessage` and `getChatMembersCount`, now aliases of the current method names.

### Removed
- `getMessageReactions()`: it is not a Bot API method.
- `ApiClient::getBotToken()`: use `getToken()`.
- `Router::match()` and `Router::parseCommand()`: use `Routing\Pattern` and `Routing\Command`.
- `config.example.php`, unused by the library.

### Migrating from 2.x
- Wrap local files in `InputFile::fromPath()`; a plain string is sent as a file_id or URL.
- `new Bot($token, $debug, $debugFile, $secretToken)` becomes `new Bot(new Config($token, secretToken: ..., debug: ...))`. `debug` takes `true` or a log file path.
- `Config`: `maxRetries`/`maxRetryDelay` become `retry: new RetryPolicy(maxRetries, maxDelay)`. Its properties are read-only.
- `WebhookValidator::validate($body, $secret, $header)` becomes `validate($secret, $header)`.
- Optional parameters now passed through `$options`:
  - `sendLocation`/`editMessageLiveLocation`: `horizontal_accuracy`, `live_period`, `heading`, `proximity_alert_radius`
  - `sendVenue`: `foursquare_*`, `google_place_*`
  - `sendPoll`: everything except `type` (the answers argument is now named `$answers`)
  - `sendAnimation`, `sendVideo`: `duration`, `width`, `height`, `thumbnail`, `supports_streaming`
  - `sendInvoice`: `provider_token`, `max_tip_amount`, `suggested_tip_amounts`
  - `answerInlineQuery`: `button` replaces `switchPmText`/`switchPmParameter`
- `promoteChatMember()` takes the rights as an array: `['can_delete_messages' => true]`.
- `createNewStickerSet()` and `addStickerToSet()` take `InputSticker` objects, as in the current API.
- Methods return `array` or `bool` instead of `array|null`; errors always throw.
- Custom `CacheInterface` implementations need `update()`, atomic for the same key. Custom `TransportInterface` implementations need `download()`.
- A handler exception goes to `onError()` listeners when there are any, and is re-thrown otherwise.

## [2.0.0] - 2026-08-17

### Changed
- Rewrote the single-file `botTG` class as a PSR-4 package: `ApiClient` with one trait per API area, `Bot`, router, middleware, events, cache, sessions and a CLI.
- Uses typed properties, named arguments and `match`.
- API requests verify TLS certificates.
- Webhook secret tokens are compared with `hash_equals()`.

## [1.0.0] - 2024

### Added
- Core `botTG` class for Telegram bot creation
- Support for webhook-based updates
- Inline keyboard builder
- Link button keyboard builder
- Keyboard merging utilities
- Command parsing and handling
- Callback query responses
- Message editing capabilities
- Photo message support
- Text templating with placeholders
- Debug mode
- IP validation for Telegram servers
- Message forwarding
- Private chat detection

### Features
- `command_simple()` - Handle simple text commands
- `simple_callback_response()` - Handle button clicks
- `send_message()` - Send messages with optional photo and keyboard
- `edit_message()` - Edit existing messages
- `build_keyboard_of_inline()` - Create inline button keyboards
- `build_keyboard_of_links()` - Create link button keyboards
- `merge_keyboards()` - Merge two keyboards
- `merge_multiple_keyboards()` - Merge multiple keyboards
- `forward_message()` - Forward messages between chats
- `forward_message_from_reply()` - Forward from reply
- `get_text_message()` - Extract message text
- `get_data()` - Extract callback data
- `get_chat_id()` - Get chat ID
- `get_message_id()` - Get message ID
- `check_text_message()` - Check if message matches
- `check_callbackquery_data()` - Check if callback matches
- `is_private()` - Check if in private chat
- `checkIp()` - Validate Telegram IP

### Examples
- `botesempio.php` - Example bot using every feature
- `echobot.php` - Simple echo bot with IP validation

### Known Issues
- Token exposed through a GET parameter in the examples
- No HTTPS enforcement
- Path traversal in photo handling
- Insufficient input validation
- File handle leak
- No rate limiting
