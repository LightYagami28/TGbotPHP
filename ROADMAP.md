# Roadmap

Stato al 2026-11-17.

This document records proposals and completed milestones aligned with [SPEC.md](https://github.com/LightYagami28/TGbotPHP/blob/main/SPEC.md).

## Obiettivi Completati

1. **Copertura Telegram Bot API 10.4 al 100%**:
   - 193 metodi API coperti con wrapper dedicati e test di conformità per parametri e tipi di ritorno (`BotApiCoverageTest`).
   - Supporto HTML Widgets (`sendWidget`, `sendSharedWidget`, `answerWidgetQuery`, `editMessageWidgetData`, `editSharedWidgetData`).
   - Supporto chat share backdrops (`getChatShareThemeBackdrops`).
   - Supporto regali unici e business account (`getUniqueGift`, `deleteBusinessAccountGift`).
   - 29 update types verificati e gestiti in `UpdateParser` (inclusi `widget_query` e `shared_widget_query`).
2. **Hardening di Sicurezza**:
   - Validazione secret token anti-spoofing obbligatoria su webhook (`403 Forbidden` per default se non configurato o errato).
   - Mitigazione CRLF log injection (CWE-117) e redazione automatica dei token sensibili (`LogRedactor::safeExceptionMessage`).
   - Zero vulnerabilità note nelle dipendenze (`composer audit` pulito).
3. **Analisi Statica e Tipizzazione**:
   - PHPStan livello 10 con `phpstan-strict-rules` e `phpstan-phpunit` a 0 errori.
   - Piena compatibilità con PHP 8.5.12+ (tipi stretti `declare(strict_types=1)` su tutti i file).
4. **Portabilità e Suite di Test**:
   - PHPUnit 13.4: 541 test, 1818 asserzioni, 0 errori, 0 fallimenti.
   - Risolto bug risoluzione percorsi Windows NTFS (`UserMethods::isLocalServerFile` e `isInsideRoot`).
   - Gestione differenziata permessi POSIX (`chmod 0555`, `0600`) su Windows NTFS.

## Milestone Future

1. **PHP 8.6 GA**:
   - Aggiornamento target CI obbligatorio non appena PHP 8.6 raggiungerà il rilascio stabile ufficiale (attualmente in sviluppo non bloccante).
2. **Estensioni di Cache Pluggabili**:
   - Mantenimento dell'architettura zero-dependency per il core (`ext-curl` e `ext-json` uniche dipendenze runtime) con adapter comunitari separati per Redis/Memcached/DB tramite `CacheInterface`.

## Fuori ambito

- Dipendenze pesanti a runtime o framework monolitici.
- Imposizione di rate-limiting Telegram lato client al di fuori del `RateLimiter` per-user.

## Proposals from SPEC

This document records proposals already identified in [SPEC.md](SPEC.md); it does not promise delivery dates or releases. Revalidate each item against current maintainers' priorities before implementation.

### Existing project scope

TGbotPHP is a PHP library for Telegram Bot API routing, middleware, and conversations. See the [README](README.md) and [architecture](docs/architecture.md) for the current documented scope.

### Review

Update this page when maintainers approve or close an item. The testing snapshot in project documents is time-bound and should be checked against current CI before being treated as current.
