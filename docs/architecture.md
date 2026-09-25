# Architecture

## Layers

```
┌────────────────────────────────────────────────────────────┐
│ Framework\Bot (facade)                                     │
│  Runner\WebhookHandler / Runner\LongPolling                │
│  → Kernel: events → middleware → Router → your handlers    │
├────────────────────────────────────────────────────────────┤
│ Core\ApiClient                                             │
│  Methods\* traits (sendMessage, banChatMember, ...)        │
│  Traits\HttpClientTrait: encoding, errors, 429 retries     │
├────────────────────────────────────────────────────────────┤
│ Http\TransportInterface → Http\CurlTransport (or your own) │
└────────────────────────────────────────────────────────────┘
```

- **`Core\ApiClient`** is a plain API client. Use it without the framework when you only need to send messages.
- **`Framework\Bot`** extends it with update handling. It is a thin facade: the work is done by `Kernel` (processing one update), `Router`, the runners and three traits in `Framework\Concerns` (handler registration, replies, conversations).
- **`Http\TransportInterface`** is the only I/O boundary. Tests swap it for `tests/Support/FakeTransport.php`.

## Directory Structure

```
src/
├── Cache/        CacheInterface, ArrayCache (in memory), FileCache (persistent)
├── CLI/          Console (bin/tgbot)
├── Core/         ApiClient, Config, RetryPolicy, UpdateParser
├── Exceptions/   TelegramException → ApiException → TooManyRequestsException
│                                  → InvalidTokenException, NetworkException,
│                                    StorageException, PluginException
├── Framework/    Bot, Kernel, Router, MiddlewarePipeline, EventDispatcher
│   ├── Concerns/ RegistersHandlers, RespondsToUpdates, ManagesConversations
│   ├── Routing/  Command, Pattern, PatternTable
│   └── Runner/   WebhookHandler, LongPolling
├── Http/         TransportInterface, CurlTransport, HttpResponse
├── Methods/      one trait per API area (Message, Media, Chat, Admin, Sticker, ...)
├── Plugin/       PluginInterface, BotPluginInterface, PluginManager
├── Rate/         RateLimiter (+ middleware)
├── Security/     WebhookValidator (secret token, IP ranges, Mini App data)
├── Session/      SessionManager, ConversationManager
├── Support/      Value (typed reads of mixed data), Payload (chat, user, topic of an update)
├── Traits/       HttpClientTrait
├── Types/        InputFile
└── Utilities/    Keyboard, InlineKeyboard, Formatter, MessageParser, Logger, BotBuilder
```

## Request lifecycle (outgoing)

1. A method trait builds the parameter array and merges the caller's `$options` into it.
2. `HttpClientTrait::prepareFields()` encodes the parameters:
   - `null` values are dropped
   - booleans become `"true"` / `"false"`
   - arrays and `JsonSerializable` objects are JSON encoded
   - `InputFile` objects become `CURLFile` uploads. Nested ones become `attach://fileN` references, and the request switches to `multipart/form-data`.
3. The transport sends the request. The response is decoded whatever the HTTP status, so Telegram's error `description` is never lost.
4. `ok: false` throws `ApiException`, or `TooManyRequestsException` for 429. A 429 is retried after `retry_after` seconds, as allowed by `Config::$retry` (a `RetryPolicy`).
5. The `result` field is returned.

## Update lifecycle (incoming)

1. `handle()` validates the secret token and parses the JSON. `poll()` calls `getUpdates` and tracks the offset.
2. `processUpdate()` dispatches `update.received`, then runs the middleware pipeline.
3. The router picks a handler:
   - **message**: command, then conversation state, then `hears` pattern, then `fallback`
   - **callback_query** and **inline_query**: exact data, then wildcard and regex patterns
   - **anything else**, or nothing matched: `onUpdate($type)` handlers
4. `update.processed` is dispatched. If anything throws, the exception goes to `error` listeners, or is re-thrown when there are none.

## Design choices

- **No runtime dependencies**: only ext-curl and ext-json.
- **Payloads stay `stdClass`**: updates are not mapped to classes, so new API fields work immediately.
- **Forward compatible**: `$options` on methods, `call()` for new methods, `onUpdate()` for new update types.
- **Backwards compatible**: deprecated method names are thin aliases, and handlers written as `function ($message)` keep working.
