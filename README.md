# TGbotPHP - Professional Telegram Bot Framework

> A production-ready, feature-complete Telegram Bot Framework for PHP 8.2+

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)]()
[![Build Status](https://img.shields.io/badge/Build-Passing-brightgreen)]()

## Features

### Complete API Coverage
- **120+ Telegram Bot API Methods** fully implemented
- Message, Chat, User, Media, Admin, and Webhook management
- Sticker, Payment, Game, Forum, Custom, Reaction, and Location methods

### Professional Architecture
- **PSR-4** autoloading with modular design
- **Trait-based composition** for 120+ API methods
- **Event-driven architecture** with lifecycle hooks
- **Middleware pipeline** for request processing
- **Router-based** command and callback handling

### Security First
- **HTTPS enforcement** for webhooks
- **Token validation** with secret tokens
- **Input sanitization** and XSS protection
- **Rate limiting** to prevent abuse
- **Session management** for multi-step flows
- **Webhook signature validation**

### Developer Experience
- **CLI tool** for webhook and bot management
- **BotBuilder** pattern for fluent configuration
- **Keyboard helpers** for inline buttons
- **Message parser** for entity extraction
- **Comprehensive logging** system
- **Plugin system** for extensibility

### Performance & Reliability
- **In-memory caching** system
- **Request rate limiting**
- **Session persistence**
- **Async webhook support**
- **Long polling fallback**
- **Error handling** with custom exceptions

### Production Ready
- **Docker support** with Dockerfile and docker-compose
- **CI/CD pipelines** with GitHub Actions
- **Comprehensive testing**
- **PHPStan** static analysis
- **Complete documentation** for all features

## Table of Contents

- [Quick Start](#quick-start)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Usage Examples](#usage-examples)
- [API Reference](#api-reference)
- [Advanced Features](#advanced-features)
- [Deployment](#deployment)
- [Security](#security)
- [Contributing](#contributing)

## Quick Start

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Utilities\BotBuilder;
use TGbotPHP\Utilities\Keyboard;

$bot = (new BotBuilder('YOUR_BOT_TOKEN'))
    ->withDebug('/tmp/bot.log')
    ->addCommand('start', function($bot, $message) {
        $bot->sendMessage(
            chatId: $message['chat']['id'],
            text: 'Welcome to TGbotPHP'
        );
    })
    ->addCallback('action', function($bot, $callback) {
        $bot->answerCallbackQuery(
            callbackQueryId: $callback['id'],
            text: 'Action processed'
        );
    })
    ->build();

$bot->handle();
```

## Installation

### Via Composer

```bash
composer require maule/tgbotphp
```

### Manual Installation

```bash
git clone https://github.com/LightYagami28/TGbotPHP/
cd TGbotPHP
composer install
```

### Docker

```bash
docker run -d \
  -e BOT_TOKEN=your_token \
  -p 8000:8000 \
  maule/tgbotphp:latest
```

## Core Concepts

### Bot Initialization

```php
use TGbotPHP\Framework\Bot;

$bot = new Bot('YOUR_BOT_TOKEN');

// Or use BotBuilder for configuration
$bot = (new BotBuilder('YOUR_BOT_TOKEN'))
    ->withDebug('/tmp/bot.log')
    ->withSecretToken('secret')
    ->build();
```

### Command Handling

```php
$bot->command('start', function($bot, $message) {
    $bot->sendMessage(
        chatId: $message['chat']['id'],
        text: 'Hello World'
    );
});
```

### Callback Queries

```php
$bot->callback('action_name', function($bot, $callback) {
    $bot->answerCallbackQuery(
        callbackQueryId: $callback['id'],
        text: 'Action completed'
    );
});
```

### Middleware

```php
$bot->middleware(function($update) {
    error_log('Processing update: ' . json_encode($update));
    return $update;
});
```

### Event Listeners

```php
$bot->on('message', function($message) {
    echo "Message from user: " . $message['from']['id'];
});

$bot->on('error', function($error) {
    error_log('Bot error: ' . $error->getMessage());
});
```

## Usage Examples

### Send Message

```php
$bot->sendMessage(
    chatId: 123456789,
    text: 'Hello World',
    parseMode: 'Markdown'
);
```

### Send Media

```php
$bot->sendPhoto(
    chatId: 123456789,
    photo: 'photo_file_id',
    caption: 'Photo caption'
);

$bot->sendDocument(
    chatId: 123456789,
    document: 'document_file_id',
    caption: 'Document caption'
);
```

### Inline Buttons

```php
use TGbotPHP\Utilities\Keyboard;

$markup = Keyboard::inline([
    'Yes' => 'yes',
    'No' => 'no',
]);

$bot->sendMessage(
    chatId: $chatId,
    text: 'Do you like this?',
    replyMarkup: $markup
);
```

### Grid Buttons

```php
$markup = Keyboard::grid([
    '1' => 'one',
    '2' => 'two',
    '3' => 'three',
], cols: 3);
```

### Message Parsing

```php
use TGbotPHP\Utilities\MessageParser;

$command = MessageParser::parseCommand('/start args');
$mentions = MessageParser::extractMentions('@user1 @user2');
$hashtags = MessageParser::extractHashtags('#awesome #framework');
$urls = MessageParser::extractUrls('Visit https://github.com');
```

### Admin Commands

```php
$bot->kickChatMember(
    chatId: -1001234567890,
    userId: 123456789
);

$bot->promoteChatMember(
    chatId: -1001234567890,
    userId: 123456789,
    canDeleteMessages: true,
    canRestrictMembers: true
);
```

### Webhook Management

```php
// Set webhook
$bot->setWebhook('https://example.com/webhook.php');

// Get webhook info
$info = $bot->getWebhookInfo();

// Delete webhook
$bot->deleteWebhook();
```

## API Reference

### Message Methods
- `sendMessage()` - Send text message
- `editMessageText()` - Edit message text
- `deleteMessage()` - Delete message
- `forwardMessage()` - Forward message
- `copyMessage()` - Copy message
- `pinChatMessage()` - Pin message

### Media Methods
- `sendPhoto()` - Send photo
- `sendDocument()` - Send document
- `sendVideo()` - Send video
- `sendAudio()` - Send audio
- `sendAnimation()` - Send animation
- `sendVoice()` - Send voice message
- `sendVideoNote()` - Send video note

### Chat Methods
- `getChat()` - Get chat info
- `getChatMember()` - Get member info
- `getChatMemberCount()` - Get member count
- `setChatTitle()` - Set chat title
- `setChatDescription()` - Set description
- `leaveChat()` - Leave chat

### User Methods
- `getMe()` - Get bot info
- `getUser()` - Get user info
- `setMyName()` - Set bot name
- `setMyDescription()` - Set bot description
- `setMyDefaultAdministratorRights()` - Set admin rights

### Admin Methods
- `kickChatMember()` - Kick member
- `unbanChatMember()` - Unban member
- `promoteChatMember()` - Promote member
- `restrictChatMember()` - Restrict member
- `deleteChatPhoto()` - Delete chat photo
- `setChatStickerSet()` - Set sticker set

### Webhook Methods
- `setWebhook()` - Set webhook URL
- `getWebhookInfo()` - Get webhook info
- `deleteWebhook()` - Delete webhook
- `getUpdates()` - Get updates (polling)

[See Full API Reference](API_REFERENCE.md)

## Advanced Features

### Rate Limiting

```php
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Cache\ArrayCache;

$limiter = new RateLimiter(new ArrayCache());

if ($limiter->limit("user:$userId", 10, 60)) {
    // Allow request
} else {
    // Rate limited
}
```

### Session Management

```php
use TGbotPHP\Session\SessionManager;

$sessions = new SessionManager(new ArrayCache());
$sessionId = $sessions->startSession($userId);
$sessions->setSessionData($sessionId, 'state', 'waiting_input');
```

### Plugin System

```php
use TGbotPHP\Plugin\PluginInterface;

class MyPlugin implements PluginInterface {
    public function getName(): string { return 'MyPlugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function activate(): void { /* init */ }
    public function deactivate(): void { /* cleanup */ }
}

$manager = new PluginManager();
$manager->register('my-plugin', new MyPlugin());
```

### Logging

```php
use TGbotPHP\Utilities\Logger;

$logger = new Logger('/tmp/bot.log');
$logger->info('User action', ['user_id' => 123]);
$logger->warning('High memory usage');
$logger->error('API error', ['code' => 400]);
```

[See Advanced Features](ADVANCED_FEATURES.md)

## Deployment

### Shared Hosting

1. Upload files via FTP
2. Run `composer install --no-dev`
3. Create `public/webhook.php`
4. Set webhook: `curl https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://example.com/webhook.php`

### Docker

```bash
docker-compose up -d
```

### VPS with Nginx

[See Deployment Guide](DEPLOYMENT.md)

### Systemd Service

Create service file and enable:

```bash
sudo systemctl enable tgbot
sudo systemctl start tgbot
```

## Security

### Best Practices

1. Always use HTTPS for webhooks
2. Validate webhook tokens and signatures
3. Implement rate limiting
4. Sanitize user input
5. Use environment variables for tokens
6. Enable logging for audit trails
7. Keep dependencies updated

### Webhook Validation

```php
use TGbotPHP\Security\WebhookValidator;

$token = getenv('BOT_SECRET_TOKEN');
$xToken = WebhookValidator::getSecretToken();

if (WebhookValidator::validate($body, $token, $xToken)) {
    $bot->handle();
}
```

[See Security Guide](SECURITY.md)

## Testing

Run unit tests:

```bash
bash run-unit-tests.sh
```

Run specific test:

```bash
php tests/BotTest.php
```

## Development

See [DEVELOPMENT.md](DEVELOPMENT.md) for:
- Project structure
- Development setup
- Adding new features
- Testing guidelines
- Code standards

## Documentation

- [INSTALLATION.md](INSTALLATION.md) - Installation guide
- [ARCHITECTURE.md](ARCHITECTURE.md) - Architecture details
- [API_REFERENCE.md](API_REFERENCE.md) - Complete API documentation
- [ADVANCED_FEATURES.md](ADVANCED_FEATURES.md) - Advanced usage
- [SECURITY.md](SECURITY.md) - Security best practices
- [DEVELOPMENT.md](DEVELOPMENT.md) - Development guide
- [DEPLOYMENT.md](DEPLOYMENT.md) - Deployment guide
- [TESTING.md](TESTING.md) - Testing guide
- [CONTRIBUTING.md](CONTRIBUTING.md) - Contributing guidelines
- [CHANGELOG.md](CHANGELOG.md) - Version history

## Project Statistics

- **Lines of Code**: 8,000+
- **API Methods**: 120+
- **Trait Modules**: 13+
- **Test Coverage**: Comprehensive
- **Documentation**: Complete
- **PHP Version**: 8.2 - 8.4+
- **PSR Standards**: PSR-1, PSR-2, PSR-4, PSR-12
- **License**: MIT

## Use Cases

- Customer support bots
- Notification systems
- Inline search bots
- Game bots
- Administrative bots
- Marketing bots
- Payment bots
- Analytics dashboards
- Content delivery bots

## Contributing

Contributions welcome! Please:

1. Fork repository
2. Create feature branch
3. Make changes
4. Add tests
5. Submit pull request

[See Contributing Guide](CONTRIBUTING.md)

## License

MIT License - see LICENSE file for details

## Author

**maule** (maule2703@gmail.com) - [GitHub](https://github.com/LightYagami28)

## Resources

- [GitHub Repository](https://github.com/LightYagami28/TGbotPHP)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [PHP Documentation](https://www.php.net/)
- [PSR Standards](https://www.php-fig.org/)

---

TGbotPHP - Built for production. Designed for developers. Powered by PHP.
