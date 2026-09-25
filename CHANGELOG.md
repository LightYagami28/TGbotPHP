# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-09-24

### Fixed
- Methods returning `True` (`deleteMessage`, `banChatMember`, `setWebhook`, ...) no longer throw a `TypeError` under `strict_types`. The same fix covers `getChatMemberCount`, which returns an integer.
- File uploads now work: requests with files are sent as `multipart/form-data` instead of carrying a wrong `x-www-form-urlencoded` header, and plain requests are properly URL-encoded.
- `file_id`s and URLs can be passed to `sendPhoto`, `sendDocument`, ... Before, every string was wrapped in `CURLFile`.
- Telegram error descriptions are kept on HTTP 4xx responses. Before, they were replaced by a generic "HTTP 400" message.
- Commands registered as `start` now match `/start`. `/cmd@botname` is handled properly, and commands addressed to other bots are ignored once the username is known.
- `null` parameters are no longer sent as empty strings.
- `RateLimiter` used a window that slid forward on every hit. It now uses a real fixed window.
- `ArrayCache::has()` returned `false` for stored `null` values.
- `composer.json` required PHP ≥ 7.0 although the code needs 8.2. The `test` script pointed to a missing file, and CI ignored failures (`|| true`).
- The CLI used a wrong autoloader path when installed as a dependency, and always exited with status 0.

### Added
- `Bot::handle()`: webhook entry point with secret token validation (403) and JSON validation (400).
- `Bot::poll()`: long polling loop with backoff, 429 handling, `stop()` and `polling.*` events.
- Routing: `hears()`, `inlineQuery()`, `onUpdate()` for any update type, `fallback()`, `onUnknownCommand()`, and wildcard and regex patterns for callbacks and inline queries. Command arguments and deep-link payloads are passed to handlers.
- Handlers receive the `Bot` as second argument.
- Onion middleware (`$next`). A simple middleware can return `false` to stop processing.
- Conversations: `useConversations()`, `state()`, `setState()`, `updateStateData()`, `clearState()`, `ConversationManager`.
- `Bot::reply()` (same chat and forum topic), `Bot::answer()`, `onError()`, plugins with `BotPluginInterface::boot()`.
- `InputFile::fromPath()` and `InputFile::fromContents()`, with automatic `attach://` handling for media groups and sticker sets.
- `Http\TransportInterface` and `CurlTransport` for custom HTTP clients and testing.
- 429 retries (`Config::$maxRetries`, `$maxRetryDelay`), local Bot API server support (`Config::$apiBaseUrl`) and a configurable timeout.
- `TooManyRequestsException` and `NetworkException`. `ApiException` gains `getApiMethod()`, `getParameters()` and `getMigrateToChatId()`.
- New methods: `answerCallbackQuery`, `editMessageCaption`, `editMessageMedia`, `editMessageReplyMarkup`, `deleteMessages`, `forwardMessages`, `copyMessages`, `stopPoll`, `pinChatMessage`, `unpinChatMessage`, `getChatMemberCount`, `setChatPhoto`, `deleteChatPhoto`, `setChatPermissions`, invite links, join requests, chat sticker sets, `banChatSenderChat`, `unbanChatSenderChat`, `setMyName`, `setMyDescription`, `setMyShortDescription` and their getters, `setChatMenuButton`, `getChatMenuButton`, default administrator rights, `getForumTopicIconStickers`, `unpinAllGeneralForumTopicMessages`, `createInvoiceLink`, `refundStarPayment`, `getStarTransactions`, current sticker set methods, `logOut`, `close`, `getUserChatBoosts`, `getFileUrl`, `downloadFile`.
- A trailing `$options` array on sending and editing methods, for any optional or newer API parameter.
- `FileCache`: a persistent cache for webhooks that does not unserialize objects.
- `RateLimiter::middleware()` and `RateLimiter::availableIn()`.
- `WebhookValidator::isTelegramIp()` and `WebhookValidator::validateWebAppData()`.
- `Formatter`: HTML and MarkdownV2 escaping and formatting helpers.
- `InlineKeyboard` fluent builder, and `Keyboard::reply()`, `remove()`, `forceReply()` and `pagination()`.
- `UpdateParser::getType()`, `getPayload()`, `getChat()`, `getUser()` and `fromArray()`.
- `MessageParser::parseArguments()`.
- CLI: `commands:list`, `commands:delete`, `webhook:set --secret --drop-pending`, and the `TELEGRAM_BOT_TOKEN` environment variable.
- A PHPUnit test suite (83 tests), PHPStan configuration, and runnable examples in `examples/`.

### Changed
- Requires PHP 8.2+ with ext-curl and ext-json.
- `disableWebPagePreview` is sent as `link_preview_options`, and `switchPmText` as the `button` object of `answerInlineQuery`.
- `parse_mode` is only sent with a caption when there is a caption.
- `Config` validates the token format (`<digits>:<secret>`) and the secret token charset.
- The Dockerfile runs as a non-root user and starts the long polling example.

### Deprecated
- `kickChatMember`, `pinMessage`, `unpinMessage` and `getChatMembersCount`. They are aliases of the current method names.

### Breaking changes
- Local files must be wrapped in `InputFile::fromPath()`. A plain string is now sent as a `file_id` or URL.
- `createNewStickerSet()` and `addStickerToSet()` follow the current API: they take a list of `InputSticker` objects.
- `uploadStickerFile()` and `setChatPhoto()` take an `InputFile`.
- `getMessageReactions()` was removed. It is not a Bot API method and always failed.
- Methods that returned `array|null` now return `array`. Errors always throw.
- `Bot::command()`, `callback()`, `middleware()` and `on()` return the bot for chaining instead of `void`.
- An exception thrown by a handler goes to `error` listeners when there are any. It is re-thrown only when there are none.

## [2.0.0] - 2026-08-17

### ✨ Major Release - Secure by Default

**Complete rewrite with security hardening and PHP 8.4 features.**

### Security Fixes ✅
- ✅ HTTPS enforcement integrated
- ✅ Path traversal protection (directory validation)
- ✅ XSS protection (output escaping)
- ✅ JSON injection prevention (strict parsing)
- ✅ Webhook secret token verification (hash_equals)
- ✅ Telegram IP anti-spoofing validation
- ✅ File resource leak fixed
- ✅ SSL/TLS certificate verification
- ✅ All OWASP vulnerabilities addressed

### Added
- PHP 8.4+ features (typed properties, named arguments, match expressions, null-safe operators)
- Webhook signature verification support
- HTTPS enforcement at class level
- Input validation and sanitization
- Output escaping and template safety
- Comprehensive error handling
- File path validation for media
- URL validation for links
- Complete security documentation

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
- `botesempio.php` - Feature-complete example bot
- `echobot.php` - Simple echo bot with IP validation

### Known Issues
- ❌ Token exposure via GET parameter in examples
- ❌ No HTTPS enforcement
- ❌ Path traversal vulnerability in photo handling
- ❌ Insufficient input validation
- ❌ File resource leak (line 400)
- ❌ No rate limiting
- ❌ No security headers

## Version History

### Development Status
This library is actively developed and marked as "W.I.P. (Work in Progress)" in original documentation.

- Ready for small projects
- Actively maintained and updated
- Use in production with caution (see SECURITY.md)

---

## Upgrade Guide

### From Version < 1.0.0

If you're upgrading from earlier versions:

1. Review SECURITY.md for critical issues
2. Update token handling (don't use GET parameter)
3. Add HTTPS enforcement to webhook
4. Implement input validation
5. Consider implementing rate limiting
6. Update error handling

## Future Roadmap

### Version 1.1.0 (Planned)
- [ ] Fix file resource leak
- [ ] Add security headers
- [ ] Implement webhook signature validation
- [ ] Add rate limiting utilities
- [ ] Improve error handling
- [ ] Add inline query support

### Version 1.2.0 (Planned)
- [ ] Add more Telegram Bot API methods
- [ ] Support for inline mode
- [ ] Payment handling
- [ ] Game scores
- [ ] Sticker pack management

### Version 2.0.0 (Future)
- [ ] Namespace support
- [ ] PSR-4 autoloading
- [ ] Async request support
- [ ] Improved type hinting
- [ ] Event-based architecture

## Deprecations

Currently no deprecations.

## Security

For security vulnerabilities, see [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

**Note:** This changelog is maintained starting from version 1.0.0. Earlier development history may be incomplete.
