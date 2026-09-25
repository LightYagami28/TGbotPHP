# Development

## Project Structure

```
TGbotPHP/
├── src/
│   ├── Cache/        # CacheInterface, ArrayCache, FileCache
│   ├── CLI/          # tgbot console commands
│   ├── Core/         # ApiClient, Config, RetryPolicy, UpdateParser
│   ├── Exceptions/   # TelegramException hierarchy
│   ├── Framework/    # Bot (facade), Kernel, Router, MiddlewarePipeline, EventDispatcher
│   │   ├── Concerns/ # Bot traits: handler registration, replies, conversations
│   │   ├── Routing/  # MessageRoutes, Command, Pattern, PatternTable, Route
│   │   └── Runner/   # WebhookHandler, LongPolling
│   ├── Http/         # TransportInterface, CurlTransport
│   ├── Methods/      # Bot API method traits
│   ├── Plugin/       # Plugin interfaces and manager
│   ├── Rate/         # RateLimiter
│   ├── Security/     # WebhookValidator
│   ├── Session/      # SessionManager, ConversationManager
│   ├── Support/      # Value and Payload: typed reads of mixed data and update payloads
│   ├── Traits/       # HttpClientTrait (request encoding, errors, retries)
│   ├── Types/        # InputFile
│   └── Utilities/    # Keyboard, InlineKeyboard, Formatter, MessageParser, Logger, BotBuilder
├── bin/tgbot         # CLI entry point
├── docs/             # Guides
├── examples/         # Runnable example bots
└── tests/
    ├── Unit/         # Fast tests with a fake transport
    ├── E2E/          # Real Telegram API (needs a token)
    └── Support/      # FakeTransport, update fixtures
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

- PSR-4 autoloading, [PER Coding Style 2.0](https://www.php-fig.org/per/coding-style/) formatting, checked by PHP-CS-Fixer (`.php-cs-fixer.dist.php`):

  ```bash
  php-cs-fixer check --diff   # or: php-cs-fixer fix
  ```

- PHP 8.4+, `declare(strict_types=1)` everywhere
- Full type hints, with generics in PHPDoc (`array<string, mixed>`)
- PHPStan level 10 (max) with strict rules must pass, with no baseline and no `@phpstan-ignore`
- Read untyped data (update payloads, decoded JSON, cache entries) through `TGbotPHP\Support\Value`

## Continuous integration

| Workflow | Runs on | Checks |
|---|---|---|
| `tests.yml` | push, pull request | PHPUnit on PHP 8.4 (with coverage in the job summary), 8.5, and 8.6 (in development, non-blocking) |
| `analysis.yml` | push, pull request | PHPStan level 10 |
| `lint.yml` | push, pull request | Code style, workflows (actionlint, zizmor), links between Markdown files |
| `security.yml` | push to main, pull request, daily | `composer audit`; dependency review on pull requests |
| `docker.yml` | changes to the image inputs | Builds the image and runs smoke tests: CLI, autoload, non-root user, no dev files |
| `e2e.yml` | manual | `tests/E2E` against the real Telegram API, with secrets from the `telegram-e2e` environment |
| `release.yml` | `v*.*.*` tags | Checks that the tag matches `ApiClient::VERSION` and the CHANGELOG, runs the checks, publishes the GitHub release with the CHANGELOG notes |

Third-party actions are pinned to commit SHAs and updated by Dependabot. Workflows use read-only tokens, except `release.yml`, which can create releases.

### Releasing

1. Set `ApiClient::VERSION` and add the `## [x.y.z] - date` section to `CHANGELOG.md`.
2. Merge to `main`, then push the tag: `git tag vx.y.z && git push origin vx.y.z`.

## Contributing

See [CONTRIBUTING.md](../CONTRIBUTING.md)
