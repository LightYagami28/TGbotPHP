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
│   ├── Support/      # Value: type-safe readers for mixed data
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

1. Add it to the matching trait in `src/Methods/`.
2. Use the typed wrapper that matches the documented result: `apiCallObject()`, `apiCallList()`, `apiCallObjectOrTrue()`, `apiCallBool()`, `apiCallInt()` or `apiCallString()`. The wrapper validates the response.
3. Pass booleans, arrays and `InputFile` objects as they are. `HttpClientTrait::prepareFields()` encodes them.
4. Add a test in `tests/Unit/ApiClientTest.php` that checks the encoded fields.

## Code Standards

- PSR-4, PSR-12
- PHP 8.4+, `declare(strict_types=1)` everywhere
- Full type hints, with generics in PHPDoc (`array<string, mixed>`)
- PHPStan level 10 (max) with strict rules must pass, with no baseline and no `@phpstan-ignore`
- Read untyped data (update payloads, decoded JSON, cache entries) through `TGbotPHP\Support\Value`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md)
