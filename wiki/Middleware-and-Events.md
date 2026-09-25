# Middleware and Events

## Middleware

Middleware runs for every update, before routing, in the order it was registered. It receives the whole [Update](https://core.telegram.org/bots/api#update), not only the message.

**Filter**: return `false` to drop the update.

```php
use TGbotPHP\Core\UpdateParser;

// Only private chats
$bot->middleware(fn(stdClass $update, Bot $bot): bool => UpdateParser::getChat($update)?->type === 'private');

// Ignore banned users
$bot->middleware(function (stdClass $update, Bot $bot) use ($banned): bool {
    return !in_array(UpdateParser::getUser($update)?->id, $banned, true);
});
```

**Wrapper**: declare a third parameter to receive `$next`, and run code before and after the rest of the pipeline.

```php
$bot->middleware(function (stdClass $update, Bot $bot, callable $next): void {
    $start = microtime(true);
    $next();
    error_log(sprintf('update %d handled in %.1f ms', $update->update_id, (microtime(true) - $start) * 1000));
});
```

A wrapper that does not call `$next()` stops the update.

`UpdateParser` helps with updates of any type: `getType($update)` (`"message"`, `"callback_query"`...), `getPayload($update)`, `getChat($update)` and `getUser($update)`.

The [rate limiter](Conversations#rate-limiting) is available as a middleware.

## Errors

An exception thrown by a handler or a middleware goes to the `onError()` listeners:

```php
use TGbotPHP\Exceptions\ApiException;

$bot->onError(function (Throwable $e, ?stdClass $update, Bot $bot): void {
    if ($e instanceof ApiException && $e->getCode() === 403) {
        return; // the user blocked the bot
    }

    error_log(sprintf('[bot] %s: %s', $e::class, $e->getMessage()));
});
```

Without listeners:

- `handle()` (webhooks) logs the error and still answers 200, because an error status would make Telegram send the same update again and again;
- `poll()` logs the error and continues with the next update;
- `handleUpdate()` throws the exception to the caller.

## Events

```php
$bot->on('update.processed', fn(stdClass $update) => $metrics->increment('updates'));
```

| Event | Arguments | When |
|---|---|---|
| `update.received` | `stdClass $update` | Before middleware |
| `update.processed` | `stdClass $update` | After routing, unless a middleware dropped the update |
| `error` | `Throwable $e, ?stdClass $update, Bot $bot` | A handler, a middleware or long polling failed |
| `error.api` | `ApiException $e, stdClass $update` | The error was an `ApiException` (before `error`) |
| `polling.started` | `Bot $bot` | `poll()` starts |
| `polling.stopped` | `Bot $bot` | `poll()` returns |

Your code and plugins can dispatch their own events: `$bot->getEvents()->dispatch('order.paid', $order)`.

## Plugins

A plugin packages handlers, middleware and listeners to reuse them across bots:

```php
use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\BotPluginInterface;

final class PingPlugin implements BotPluginInterface
{
    public function getName(): string
    {
        return 'ping';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function activate(): void
    {
        // Called when the plugin is registered
    }

    public function deactivate(): void
    {
        // Called when the plugin is removed
    }

    public function boot(Bot $bot): void
    {
        $bot->command('ping', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'pong'));
    }
}

$bot->plugin(new PingPlugin());
```

`$bot->getPlugins()` returns the `PluginManager`: `get('ping')`, `isActive('ping')`, `unregister('ping')`. Its hooks pass a value through callbacks sorted by priority:

```php
$bot->getPlugins()->addHook('greeting', fn(string $text) => $text . ' 👋', priority: 20);
$greeting = $bot->getPlugins()->executeHook('greeting', 'Hello');
```
