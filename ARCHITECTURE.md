# TGbotPHP Architecture

Modular structure supporting all Telegram Bot API methods.

## Directory Structure

```
TGbotPHP/
├── src/
│   ├── Core/              # Core functionality
│   │   ├── Bot.php        # Main Bot class
│   │   ├── Config.php     # Configuration
│   │   └── UpdateParser.php
│   ├── Methods/           # API Methods (100+)
│   │   ├── MessageMethods.php
│   │   ├── ChatMethods.php
│   │   ├── FileMethods.php
│   │   ├── StickerMethods.php
│   │   ├── InlineMethods.php
│   │   ├── PaymentMethods.php
│   │   ├── GameMethods.php
│   │   ├── WebAppMethods.php
│   │   └── AdminMethods.php
│   ├── Handlers/          # Update handlers
│   │   ├── MessageHandler.php
│   │   ├── CallbackHandler.php
│   │   └── InlineHandler.php
│   ├── Exceptions/        # Custom exceptions
│   │   ├── TelegramException.php
│   │   ├── InvalidTokenException.php
│   │   └── ApiException.php
│   ├── Traits/            # Shared functionality
│   │   ├── HttpClientTrait.php
│   │   ├── LoggerTrait.php
│   │   └── ValidatorTrait.php
│   └── Utilities/         # Helpers
│       ├── Keyboard.php
│       ├── InlineQueryBuilder.php
│       └── FileValidator.php
├── botlib.php             # Legacy single-file support
├── bot-complete.php       # Full example
└── composer.json          # PSR-4 autoloading
```

## Usage

### PSR-4 Namespace

```php
use TGbotPHP\Core\Bot;
use TGbotPHP\Methods\MessageMethods;
use TGbotPHP\Exceptions\ApiException;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));
```

### Legacy Support

```php
require_once 'botlib.php';
use TGbotPHP\botTG;

$bot = new botTG(token: $token, updates: $updates);
```

## Method Categories

### 1. MessageMethods.php
- sendMessage
- forwardMessage
- copyMessage
- editMessageText
- editMessageCaption
- deleteMessage
- sendPhoto, sendAudio, sendDocument, sendVideo
- sendAnimation, sendVoice
- sendMediaGroup
- sendChatAction
- etc. (30+ methods)

### 2. ChatMethods.php
- getChat
- getChatMember, getChatMembers
- setChatTitle, setChatDescription
- setChatPermissions
- pinMessage, unpinMessage
- leaveChat
- etc. (20+ methods)

### 3. FileMethods.php
- getFile
- downloadFile
- uploadStickerFile
- etc. (5+ methods)

### 4. StickerMethods.php
- sendSticker
- getStickerSet
- uploadStickerFile
- createNewStickerSet
- addStickerToSet
- etc. (15+ methods)

### 5. InlineMethods.php
- answerInlineQuery
- answerWebAppQuery
- etc. (2 methods)

### 6. PaymentMethods.php
- sendInvoice
- answerShippingQuery
- answerPreCheckoutQuery
- etc. (3 methods)

### 7. GameMethods.php
- sendGame
- setGameScore
- getGameHighScores
- etc. (3 methods)

### 8. WebAppMethods.php
- answerWebAppQuery
- etc. (1 method)

### 9. AdminMethods.php
- kickChatMember
- banChatMember
- unbanChatMember
- restrictChatMember
- etc. (10+ methods)

## Total Coverage

✅ 100+ Telegram Bot API methods  
✅ Webhook + Long polling support  
✅ All media types (photos, videos, audio, documents, stickers, animations)  
✅ Full keyboard support (inline, reply, web app)  
✅ Payment processing  
✅ Games  
✅ Inline queries  
✅ Chat administration  

## Interface

All methods follow consistent patterns:

```php
// List/Get methods return arrays
$chat = $bot->getChat($chatId);

// Send methods return response
$response = $bot->sendMessage($chatId, "Hello");

// Action methods return boolean
$success = $bot->pinMessage($chatId, $messageId);

// Complex operations use builders
$keyboard = $bot->keyboard()
    ->inline()
    ->button("Click", "callback_data")
    ->build();
```

## Security

✅ HTTPS enforcement  
✅ Input validation  
✅ Output escaping  
✅ Path traversal protection  
✅ Webhook signature verification  
✅ IP validation  
✅ SSL/TLS verification  

## Type Safety

- Full type hints (PHP 8.4+)
- PHPStan level 5
- Strict types everywhere
- Return type declarations

## Error Handling

```php
try {
    $bot->sendMessage($chatId, "Hello");
} catch (ApiException $e) {
    echo "API error: " . $e->getMessage();
    echo "Response: " . json_encode($e->getApiResponse());
}
```

## Backwards Compatibility

Legacy `botlib.php` still works:
```php
require_once 'botlib.php';
$bot = new botTG(token: $token, updates: $updates);
```

But new code should use PSR-4:
```php
use TGbotPHP\Core\Bot;
$bot = new Bot($token);
```
