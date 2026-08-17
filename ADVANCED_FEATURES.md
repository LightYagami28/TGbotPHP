# Advanced Features Guide

## Plugin System

Extend TGbotPHP functionality with plugins:

```php
use TGbotPHP\Plugin\PluginInterface;
use TGbotPHP\Plugin\PluginManager;

class MyPlugin implements PluginInterface {
    public function getName(): string { return 'MyPlugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function activate(): void { /* ... */ }
    public function deactivate(): void { /* ... */ }
}

$manager = new PluginManager();
$manager->register('my-plugin', new MyPlugin());
```

## Caching

Use in-memory caching for performance:

```php
use TGbotPHP\Cache\ArrayCache;

$cache = new ArrayCache();
$cache->put('key', 'value', ttl: 300);
$value = $cache->get('key');
```

## Rate Limiting

Protect your bot from abuse:

```php
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Cache\ArrayCache;

$limiter = new RateLimiter(new ArrayCache());

if ($limiter->limit("user:{$userId}", maxRequests: 10, windowSeconds: 60)) {
    // Allow request
} else {
    // Rate limit exceeded
}
```

## Session Management

Manage user sessions:

```php
use TGbotPHP\Session\SessionManager;

$sessions = new SessionManager(new ArrayCache());
$sessionId = $sessions->startSession($userId);
$sessions->setSessionData($sessionId, 'state', 'waiting_input');
```

## Webhook Validation

Secure webhook handling:

```php
use TGbotPHP\Security\WebhookValidator;

$secretToken = getenv('TELEGRAM_SECRET_TOKEN');
$xToken = WebhookValidator::getSecretToken();

if (WebhookValidator::validate($body, $secretToken, $xToken)) {
    // Process update
}
```

## Message Parsing

Extract entities from messages:

```php
use TGbotPHP\Utilities\MessageParser;

$command = MessageParser::parseCommand($text);
$mentions = MessageParser::extractMentions($text);
$hashtags = MessageParser::extractHashtags($text);
$urls = MessageParser::extractUrls($text);
```

## Logging

Comprehensive logging system:

```php
use TGbotPHP\Utilities\Logger;

$logger = new Logger('/path/to/logs.log');
$logger->info('User started bot', ['user_id' => 123]);
$logger->warning('Rate limit approaching');
$logger->error('API error', ['error' => $e->getMessage()]);
```

## Keyboard Helpers

Build complex keyboards easily:

```php
use TGbotPHP\Utilities\Keyboard;

// Inline buttons
Keyboard::inline(['Yes' => 'yes', 'No' => 'no']);

// Grid layout (2 columns)
Keyboard::grid(['Btn1' => 'b1', 'Btn2' => 'b2', 'Btn3' => 'b3'], cols: 2);

// Menu with custom layout
Keyboard::menu(['Profile' => 'p', 'Settings' => 's'], itemsPerRow: 1);
```

## BotBuilder Pattern

Fluent bot configuration:

```php
use TGbotPHP\Utilities\BotBuilder;

$bot = (new BotBuilder($token))
    ->withDebug('/tmp/bot.log')
    ->withSecretToken('secret')
    ->addCommand('start', function($bot, $msg) { /* ... */ })
    ->addCallback('action', function($bot, $cb) { /* ... */ })
    ->addMiddleware(function($update) { /* ... */ })
    ->addEventListener('error', function($error) { /* ... */ })
    ->build();

$bot->handle();
```

## Security Best Practices

1. **Always validate webhook signatures** when using secret tokens
2. **Enable rate limiting** to prevent abuse
3. **Use HTTPS** for webhook URLs
4. **Validate user input** before processing
5. **Log sensitive actions** for audit trails
6. **Implement session management** for multi-step flows

## Performance Tips

1. Cache frequently accessed data (user profiles, settings)
2. Use rate limiting to prevent API throttling
3. Batch API calls when possible
4. Use webhooks instead of polling when available
5. Implement proper error handling and retries
