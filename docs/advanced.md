# Advanced Features Guide

## Middleware

Middleware runs before routing, in the order it was registered.

```php
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Framework\Bot;

// Simple middleware: return false to drop the update
$bot->middleware(function (stdClass $update, Bot $bot): bool {
    return UpdateParser::getChat($update)?->type === 'private';
});

// Onion middleware: wraps everything after it
$bot->middleware(function (stdClass $update, Bot $bot, callable $next): void {
    $bot->sendChatAction(UpdateParser::getChat($update)->id, 'typing');
    $next();
});
```

## Conversations

Multi-step dialogs keep a state per chat and user. In webhook mode, use a persistent cache.

```php
use TGbotPHP\Cache\FileCache;

$bot->useConversations(new FileCache('/var/lib/mybot/cache'), ttl: 900);

$bot->command('order', function (stdClass $message, Bot $bot) {
    $bot->setState($message, 'order:product');
    $bot->reply($message, 'What would you like?');
});

$bot->state('order:product', function (stdClass $message, Bot $bot) {
    $bot->setState($message, 'order:quantity', ['product' => $message->text]);
    $bot->reply($message, 'How many?');
});

$bot->state('order:quantity', function (stdClass $message, Bot $bot, array $data) {
    $bot->clearState($message);
    $bot->reply($message, "Ordered {$message->text} × " . Formatter::escape($data['product']));
});

// Commands are routed before states, so /cancel always works
$bot->command('cancel', function (stdClass $message, Bot $bot) {
    $bot->clearState($message);
    $bot->reply($message, 'Cancelled');
});
```

## Caching

| Cache | Use |
|---|---|
| `ArrayCache` | Long polling, tests: lost when the process ends |
| `FileCache` | Webhooks on a single server: persists between requests |
| your own `CacheInterface` | Redis, Memcached, a database... |

```php
use TGbotPHP\Cache\FileCache;

$cache = new FileCache('/var/lib/mybot/cache'); // keep it outside the web root
$cache->put('key', ['any' => 'array'], ttl: 300);
$value = $cache->get('key', default: null);
$cache->prune(); // delete expired entries (e.g. from a cron job)
```

`FileCache` never unserializes objects. Store scalars and arrays.

`update()` reads, changes and writes an entry as one step. `FileCache` holds a file lock meanwhile, so two webhook requests from the same user cannot overwrite each other's changes. Conversations, sessions and the rate limiter use it.

```php
$cache->update('visits', fn(mixed $count) => (int) $count + 1);
```

With your own `CacheInterface` on Redis or a database, make `update()` atomic too, for example with a transaction or a lock.

## Rate Limiting

```php
use TGbotPHP\Rate\RateLimiter;

$limiter = new RateLimiter($cache);

// As middleware: 10 updates per user every 60 seconds
$bot->middleware($limiter->middleware(10, 60, function (stdClass $update, Bot $bot) {
    // optional: called for dropped updates
}));

// Manually
if (!$limiter->limit("search:$userId", maxRequests: 3, windowSeconds: 60)) {
    $bot->reply($message, 'Slow down! Try again in ' . $limiter->availableIn("search:$userId") . 's');
}
```

## Plugins

```php
use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\BotPluginInterface;

final class AdminPlugin implements BotPluginInterface
{
    public function __construct(private array $admins) {}

    public function getName(): string { return 'admin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function activate(): void {}
    public function deactivate(): void {}

    public function boot(Bot $bot): void
    {
        $bot->command('stats', function (stdClass $message, Bot $bot) {
            if (in_array($message->from->id, $this->admins, true)) {
                $bot->reply($message, 'Stats: ...');
            }
        });
    }
}

$bot->plugin(new AdminPlugin([123456]));
```

`PluginManager` also provides priority-ordered hooks (`addHook()` and `executeHook()`) for plugins that need to talk to each other.

## Error Handling

```php
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\TooManyRequestsException;

$bot->onError(function (Throwable $e, ?stdClass $update, Bot $bot) {
    if ($e instanceof ApiException && $e->getCode() === 403) {
        return; // the user blocked the bot
    }

    error_log($e);
});

try {
    $bot->sendMessage($groupId, 'Hi');
} catch (ApiException $e) {
    if ($newId = $e->getMigrateToChatId()) {
        $bot->sendMessage($newId, 'Hi'); // group upgraded to supergroup
    }
}
```

429 errors are retried automatically. Change the policy with `new Config($token, retry: new RetryPolicy(maxRetries: 3, maxDelay: 60))`, or disable it with `RetryPolicy::none()`.

## Local Bot API server

```php
$bot = new Bot(new Config(
    token: $token,
    apiBaseUrl: 'http://localhost:8081',
    enforceHttps: false,
    timeout: 120,
));
```

## Custom HTTP transport

Implement `TGbotPHP\Http\TransportInterface` (`post()`, `get()` and `download()`, which writes a response to a file) to use another HTTP client, or to fake Telegram in tests. `CurlTransport` reuses its connection between requests.

```php
$bot = new Bot($token, new MyTransport());
```

## Mini Apps

```php
use TGbotPHP\Security\WebhookValidator;

$data = WebhookValidator::validateWebAppData($_POST['initData'], $token, maxAge: 3600);
if ($data === null) {
    http_response_code(403);
    exit;
}
$user = json_decode($data['user'], true);
```

## Logging

```php
use TGbotPHP\Utilities\Logger;

$logger = new Logger('/var/log/mybot.log', minLevel: 'INFO');
$logger->info('User {id} started the bot', ['id' => $userId]);
```

The logger escapes line breaks in messages, so user input cannot forge log entries.
