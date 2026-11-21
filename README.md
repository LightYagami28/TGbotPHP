# TGbotPHP

Libreria PHP per la [Telegram Bot API](https://core.telegram.org/bots/api), con routing, middleware e conversazioni per costruire bot.

[![Tests](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/tests.yml)
[![Code Analysis](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml/badge.svg)](https://github.com/LightYagami28/TGbotPHP/actions/workflows/analysis.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-777bb4)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen)](https://phpstan.org/)
[![Telegram Bot API](https://img.shields.io/badge/Bot%20API-10.4-2CA5E0?logo=telegram)](https://core.telegram.org/bots/api)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache--2.0-blue)](LICENSE)

Fork di [OpenTelegramFiles/TGbotPHP](https://github.com/OpenTelegramFiles/TGbotPHP).

## Requisiti

- PHP 8.5 o successivo, con estensioni `curl` e `json`.
- Un endpoint HTTPS solo se si ricevono aggiornamenti tramite webhook.
- Nessuna dipendenza runtime di terze parti.

## Installazione

```bash
composer require lightyagami28/tgbotphp
```

## Uso rapido

```php
use TGbotPHP\Framework\Bot;

$bot = new Bot($_ENV['BOT_TOKEN'], $_ENV['WEBHOOK_SECRET']);
$bot->command('/start', fn ($update, $bot) => $bot->sendMessage(
    chatId: $update->message->chat->id,
    text: 'Ciao!',
));
$bot->handle();
```

Per il polling locale usa `LongPolling`; per i test usa `BotTester` e `FakeTransport`. La documentazione completa è in [`docs/`](docs/README.md) e nel [`wiki/`](wiki/Home.md).

## Sicurezza

- Token e secret webhook validati; il secret è obbligatorio per impostazione predefinita.
- Firma HMAC e firma Ed25519 dei Mini App verificati con confronto a tempo costante.
- Limiti di dimensione del body, timeout HTTP, rate limiting per utente.
- Log strutturati con redazione automatica dei segreti e prevenzione del log injection.
- Nessuna deserializzazione di oggetti dalla cache, nessun redirect HTTP, TLS verificato.

Per le segnalazioni vedi [SECURITY.md](SECURITY.md).

## Verifiche

```bash
composer install
composer check
php -l src/Core/Config.php
composer audit
```

`composer check` esegue PHPUnit e PHPStan livello 10: entrambi devono terminare senza errori e senza warning.

## Licenza

Apache License 2.0. Vedi [LICENSE](LICENSE) e [NOTICE.md](NOTICE.md).

## Altri documenti

[ROADMAP.md](ROADMAP.md), [CONTRIBUTING.md](CONTRIBUTING.md), [CHANGELOG.md](CHANGELOG.md), [SPEC.md](SPEC.md), [ARCHITECTURE.md](ARCHITECTURE.md), [OBSERVABILITY.md](OBSERVABILITY.md), [FAQ.md](FAQ.md), [SUPPORT.md](SUPPORT.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), [CITATION.cff](CITATION.cff).
