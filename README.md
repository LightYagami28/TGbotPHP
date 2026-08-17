# TGbotPHP

Professional Telegram Bot Framework for PHP 8.2+

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Build Status](https://img.shields.io/badge/Build-Passing-brightgreen)]()
[![Version](https://img.shields.io/badge/Version-2.0.0-blue)]()
[![Maintenance](https://img.shields.io/badge/Maintenance-Active-green)]()

> Production-ready. Feature-complete. Security-first.

## Quick Overview

TGbotPHP is a comprehensive framework implementing 120+ Telegram Bot API methods with modern PHP practices.

**Features:**
- Complete Telegram Bot API (120+ methods)
- Security hardened (webhooks, validation, rate limiting)
- Professional architecture (PSR-4, traits, middleware)
- Plugin system & extensibility
- Comprehensive documentation
- Zero external dependencies

## Installation

```bash
git clone https://github.com/LightYagami28/TGbotPHP.git
cd TGbotPHP
composer install
```

## Quick Start

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Utilities\BotBuilder;

$bot = (new BotBuilder('YOUR_BOT_TOKEN'))
    ->addCommand('start', function($bot, $message) {
        $bot->sendMessage(
            chatId: $message['chat']['id'],
            text: 'Welcome to TGbotPHP'
        );
    })
    ->build();

$bot->handle();
```

## Documentation

- **[Getting Started](docs/INSTALLATION.md)** - Setup and first bot
- **[API Reference](docs/API_REFERENCE.md)** - Complete method documentation
- **[Security Guide](docs/SECURITY.md)** - Security best practices
- **[Deployment](docs/DEPLOYMENT.md)** - Production deployment
- **[Advanced Features](docs/ADVANCED_FEATURES.md)** - Plugins, caching, sessions
- **[Wiki](https://github.com/LightYagami28/TGbotPHP/wiki)** - Tutorials and guides

## Requirements

- PHP 8.2 or higher
- cURL extension
- HTTPS enabled server (for webhooks)

## Project Stats

- **API Methods:** 120+
- **Code:** 8,000+ lines
- **PSR Standards:** PSR-1, PSR-2, PSR-4, PSR-12
- **Test Coverage:** Comprehensive
- **License:** MIT

## Repository

- **GitHub:** https://github.com/LightYagami28/TGbotPHP
- **Issues:** https://github.com/LightYagami28/TGbotPHP/issues

## Author

LightYagami28 (ceo@retechrevive.it)

## License

MIT License - See LICENSE file
