# Conversations

A conversation asks the user several questions in a row: a sign-up form, an order, a quiz. The bot keeps a **state** for each user in each chat, and routes their next message to the handler of that state.

## Example: an order form

```php
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Support\Value;
use TGbotPHP\Utilities\Formatter;

$bot->useConversations(new ArrayCache(), ttl: 900);

$bot->command('order', function (stdClass $message, Bot $bot): void {
    $bot->setState($message, 'order:product');
    $bot->reply($message, 'What would you like to order?');
});

$bot->state('order:product', function (stdClass $message, Bot $bot): void {
    $bot->setState($message, 'order:quantity', ['product' => Value::string($message->text ?? null)]);
    $bot->reply($message, 'How many?');
});

$bot->state('order:quantity', function (stdClass $message, Bot $bot, array $data): void {
    $quantity = Value::int($message->text ?? null);

    if ($quantity < 1) {
        $bot->reply($message, 'Please send a number, for example 2.');
        return; // the state does not change: the next message is a new answer
    }

    $bot->clearState($message);
    $bot->reply($message, "Ordered $quantity × " . Formatter::escape(Value::string($data['product'])));
});

// Commands are routed before states, so /cancel always works
$bot->command('cancel', function (stdClass $message, Bot $bot): void {
    $bot->clearState($message);
    $bot->reply($message, 'Cancelled.');
});
```

| Method | Does |
|---|---|
| `useConversations($cache, $ttl)` | Enables conversations. The state expires after `$ttl` seconds without answers |
| `state($name, $handler)` | Handles the messages of users in that state. The handler receives the data saved with the state |
| `setState($payload, $state, $data)` | Moves the user to a state, replacing its data |
| `updateStateData($payload, $data)` | Merges data into the current state |
| `clearState($payload)` | Ends the conversation |
| `conversations()` | The `ConversationManager`, to read or change the state of any chat and user |

`$payload` is the message or callback query of the user; the state belongs to that user in that chat. In a group, each member has their own state.

## Where states are stored

States live in a cache that implements `CacheInterface`:

| Cache | Use it for |
|---|---|
| `ArrayCache` | Long polling and tests. Lost when the process ends |
| `FileCache` | Webhooks on one server. Persists between requests |
| Your own `CacheInterface` | Several servers: Redis, Memcached, a database |

**With webhooks, use a persistent cache.** Each update is a new PHP process, so an `ArrayCache` would forget the state after every message.

```php
use TGbotPHP\Cache\FileCache;

$bot->useConversations(new FileCache('/var/lib/mybot/cache'));
```

Keep the directory outside the web root. `FileCache` never unserializes objects, so only scalars and arrays are stored. Call `$cache->prune()` from a cron job to delete expired entries.

### Concurrent updates

Two webhook requests from the same user can run at the same time, for example when they send an album or tap two buttons quickly. Reading a state, changing it and writing it back would then lose one of the changes. `CacheInterface::update()` does the three steps at once, and `FileCache` holds a file lock meanwhile:

```php
$cache->update('visits', fn(mixed $count) => (int) $count + 1);
```

Conversations, sessions and the rate limiter use `update()`. If you write your own cache, make `update()` atomic too: `WATCH`/`MULTI` or a Lua script with Redis, a transaction or `SELECT ... FOR UPDATE` with a database.

## Sessions

`SessionManager` stores data under a random session id, for flows that are not tied to one chat, such as a login link opened in a browser:

```php
use TGbotPHP\Session\SessionManager;

$sessions = new SessionManager($cache);

$sessionId = $sessions->startSession($userId);   // expires after one hour
$sessions->setSessionData($sessionId, 'lang', 'it');
$lang = $sessions->getSessionData($sessionId, 'lang', default: 'en');
$sessions->endSession($sessionId);
```

## Rate limiting

`RateLimiter` counts requests in fixed time windows.

```php
use TGbotPHP\Rate\RateLimiter;

$limiter = new RateLimiter($cache);

// Every update: at most 20 per user per minute; the others are dropped
$bot->middleware($limiter->middleware(20, 60, function (stdClass $update, Bot $bot): void {
    // optional: called for each dropped update
}));

// One expensive command: 3 searches per user per minute
$bot->command('search', function (stdClass $message, Bot $bot, string $query) use ($limiter): void {
    $key = 'search:' . $message->from->id;

    if (!$limiter->limit($key, maxRequests: 3, windowSeconds: 60)) {
        $bot->reply($message, 'Too many searches. Try again in ' . $limiter->availableIn($key) . ' seconds.');
        return;
    }

    // ...
});
```

`remaining($key, $maxRequests)` returns how many requests are left in the current window, and `reset($key)` clears it.

Telegram also limits your bot: about 30 messages per second overall and 1 per second in the same chat. When a request exceeds them, the library waits and retries as the [retry policy](Configuration#retries) allows.
