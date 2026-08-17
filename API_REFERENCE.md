# TGbotPHP API Reference

## Core Classes

### Bot
Main bot orchestrator class.

```php
$bot = new TGbotPHP\Framework\Bot($token);

// Send message
$bot->sendMessage(chatId: 123, text: 'Hello');

// Handle updates
$bot->handle();

// Register command
$bot->command('start', $callback);

// Register callback
$bot->callback('action', $callback);

// Add middleware
$bot->middleware($handler);

// Add event listener
$bot->on('update', $handler);
```

### Config
Configuration management.

```php
$config = new TGbotPHP\Core\Config($token);
$valid = $config->isValid();
$token = $config->getToken();
```

### UpdateParser
Parse webhook updates.

```php
$parser = new TGbotPHP\Core\UpdateParser();
$update = $parser->parse($_POST);
$isValid = $parser->validate($_POST);
```

### Router
Route commands and callbacks.

```php
$router = new TGbotPHP\Framework\Router();
$router->command('start', $callback);
$router->callback('action', $callback);
$route = $router->match($update);
```

## Message Methods

### sendMessage
```php
$bot->sendMessage(
    chatId: 123,
    text: 'Hello World',
    parseMode: 'HTML',
    replyMarkup: [...],
    disableNotification: false
);
```

### editMessageText
```php
$bot->editMessageText(
    chatId: 123,
    messageId: 456,
    text: 'Updated',
    replyMarkup: [...]
);
```

### deleteMessage
```php
$bot->deleteMessage(chatId: 123, messageId: 456);
```

### forwardMessage
```php
$bot->forwardMessage(
    chatId: 123,
    fromChatId: 456,
    messageId: 789
);
```

## Chat Methods

### getChat
```php
$chat = $bot->getChat(chatId: 123);
```

### getChatMember
```php
$member = $bot->getChatMember(chatId: 123, userId: 456);
```

### setChatTitle
```php
$bot->setChatTitle(chatId: 123, title: 'New Title');
```

### setChatDescription
```php
$bot->setChatDescription(chatId: 123, description: 'Description');
```

## User Methods

### getMe
```php
$me = $bot->getMe();
// Returns: {id, first_name, username, is_bot, ...}
```

### getUser
```php
$user = $bot->getUser(userId: 123);
```

## Callback Methods

### answerCallbackQuery
```php
$bot->answerCallbackQuery(
    callbackQueryId: 'query_id',
    text: 'Response',
    showAlert: false
);
```

## Media Methods

### sendPhoto
```php
$bot->sendPhoto(
    chatId: 123,
    photo: 'photo_file_id',
    caption: 'Photo caption'
);
```

### sendDocument
```php
$bot->sendDocument(
    chatId: 123,
    document: 'document_file_id',
    caption: 'Document caption'
);
```

### sendVideo
```php
$bot->sendVideo(
    chatId: 123,
    video: 'video_file_id',
    caption: 'Video caption',
    duration: 60
);
```

### sendAudio
```php
$bot->sendAudio(
    chatId: 123,
    audio: 'audio_file_id',
    title: 'Song Title'
);
```

## Admin Methods

### kickChatMember
```php
$bot->kickChatMember(
    chatId: 123,
    userId: 456,
    untilDate: time() + 3600
);
```

### unbanChatMember
```php
$bot->unbanChatMember(chatId: 123, userId: 456);
```

### promoteChatMember
```php
$bot->promoteChatMember(
    chatId: 123,
    userId: 456,
    canDeleteMessages: true,
    canRestrictMembers: true
);
```

## Webhook Methods

### setWebhook
```php
$bot->setWebhook(
    url: 'https://example.com/webhook.php',
    certificate: null,
    ipAddress: null,
    maxConnections: 40,
    allowedUpdates: ['message', 'callback_query']
);
```

### getWebhookInfo
```php
$info = $bot->getWebhookInfo();
// Returns: {url, has_custom_certificate, pending_update_count, ...}
```

### deleteWebhook
```php
$bot->deleteWebhook();
```

## Utilities

### Keyboard
```php
use TGbotPHP\Utilities\Keyboard;

Keyboard::inline(['Yes' => 'yes', 'No' => 'no']);
Keyboard::grid($buttons, cols: 2);
Keyboard::menu($items, itemsPerRow: 1);
Keyboard::links(['GitHub' => 'https://github.com']);
Keyboard::row($buttons);
```

### MessageParser
```php
use TGbotPHP\Utilities\MessageParser;

$cmd = MessageParser::parseCommand($text);
$mentions = MessageParser::extractMentions($text);
$hashtags = MessageParser::extractHashtags($text);
$urls = MessageParser::extractUrls($text);
$emails = MessageParser::extractEmails($text);
```

### BotBuilder
```php
use TGbotPHP\Utilities\BotBuilder;

$bot = (new BotBuilder($token))
    ->withDebug('/tmp/bot.log')
    ->addCommand('start', $handler)
    ->addCallback('action', $handler)
    ->build();
```

### Logger
```php
use TGbotPHP\Utilities\Logger;

$logger = new Logger('/tmp/bot.log');
$logger->info('Message', ['context' => 'value']);
$logger->warning('Warning');
$logger->error('Error', ['details' => '...']);
$logger->debug('Debug info');
```

## Security

### WebhookValidator
```php
use TGbotPHP\Security\WebhookValidator;

$valid = WebhookValidator::validate($body, $secretToken, $xToken);
$token = WebhookValidator::getSecretToken();
```

### RateLimiter
```php
use TGbotPHP\Rate\RateLimiter;

$limiter = new RateLimiter(new ArrayCache());
if ($limiter->limit("user:$id", 10, 60)) {
    // Allow
}
```

### SessionManager
```php
use TGbotPHP\Session\SessionManager;

$sessions = new SessionManager(new ArrayCache());
$sessionId = $sessions->startSession($userId);
$sessions->setSessionData($sessionId, 'state', 'waiting');
```

## Events

Available events:
- `update` - When an update is received
- `message` - When a message is received
- `callback_query` - When a callback query arrives
- `command` - Before a command is executed
- `error` - When an error occurs

```php
$bot->on('update', function($update) { /* ... */ });
$bot->on('error', function($error) { /* ... */ });
```
