# Development Guide

## Project Structure

```
TGbotPHP/
├── src/
│   ├── Cache/        # CacheInterface, ArrayCache, FileCache
│   ├── CLI/          # tgbot console commands
│   ├── Core/         # ApiClient, Config, UpdateParser
│   ├── Exceptions/   # TelegramException hierarchy
│   ├── Framework/    # Bot, Router, MiddlewarePipeline, EventDispatcher
│   ├── Http/         # TransportInterface, CurlTransport
│   ├── Methods/      # Bot API method traits
│   ├── Plugin/       # Plugin interfaces and manager
│   ├── Rate/         # RateLimiter
│   ├── Security/     # WebhookValidator
│   ├── Session/      # SessionManager, ConversationManager
│   ├── Traits/       # HttpClientTrait (request encoding, errors, retries)
│   ├── Types/        # InputFile
│   └── Utilities/    # Keyboard, InlineKeyboard, Formatter, MessageParser, Logger, BotBuilder
├── bin/tgbot         # CLI entry point
├── examples/         # Runnable example bots
└── tests/            # PHPUnit test suite
```

## Setup

```bash
composer install
composer check
```

## Adding an API method

1. Add it to the matching trait in `src/Methods/` using `$this->apiCall('methodName', [...], $options)`.
2. Return `bool` for methods that return `True`, and an array for objects. Cast with `(bool)`, `(int)` or `(string)` when needed.
3. Pass booleans, arrays and `InputFile` objects as they are. `HttpClientTrait::prepareFields()` encodes them.
4. Add a test in `tests/Unit/ApiClientTest.php` that checks the encoded fields.

## Code Standards

- PSR-4, PSR-12
- PHP 8.2+, `declare(strict_types=1)` everywhere
- Full type hints, with generics in PHPDoc (`array<string, mixed>`)
- PHPStan level 5 must pass

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md)
