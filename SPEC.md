# SPEC: TGbotPHP

## Obiettivo

TGbotPHP è finito quando la libreria PHP (pacchetto Composer
`lightyagami28/tgbotphp`, namespace `TGbotPHP\`) copre tutti i metodi e i tipi
di aggiornamento dell'ultima versione della Bot API di Telegram, con coverage di
riga al 100%, PHPStan livello 10 senza errori, zero avvisi nei test e
documentazione allineata al codice. Non ha dipendenze a runtime oltre a
`ext-curl` e `ext-json`.

## Stato attuale verificato

Letto dai file il 2026-11-11, ramo `main`. Repo pubblico, licenza Apache-2.0,
fork di OpenTelegramFiles/TGbotPHP.

| Componente | Valore | File |
| --- | --- | --- |
| PHP | `>=8.5` (CI su 8.5; 8.6 sperimentale e non bloccante) | `composer.json`, `.github/workflows/tests.yml` |
| Estensioni | `curl`, `json` obbligatorie; `sodium` facoltativa | `composer.json` |
| Bot API coperta | 10.3 | `tools/bot-api.json` |
| PHPUnit | ^13.4 | `composer.json` |
| PHPStan | ^2.3, livello 10 (+ phpstan-phpunit ^2.1, phpstan-strict-rules ^2.1) | `composer.json`, `phpstan.neon.dist` |
| Stile del codice | PER-CS 2.0 con php-cs-fixer | `.php-cs-fixer.dist.php`, `lint.yml` |
| Docker | `php:8.5-cli`, multi-stage, utente non root | `Dockerfile` |
| actions/checkout | v7.0.1 (SHA) | `tests.yml` |
| shivammathur/setup-php | 2.40.0 (SHA) | `tests.yml` |

Workflow presenti: `tests` (PHPUnit), `analysis` (PHPStan), `lint`
(php-cs-fixer, actionlint, zizmor), `security` (push, PR e ogni giorno alle
05:17), `bot-api` (ogni lunedì alle 06:17), `docker`, `e2e` (solo manuale,
contro la API reale), `release` (tag `v*.*.*`), `wiki`, `sonar-linux` (push e
PR), `sonar-macos` e `sonar-windows` (solo manuali). Dependabot:
`github-actions`, `composer`, `docker`.

PHPUnit è configurato con `failOnWarning`, `failOnDeprecation`, `failOnNotice`,
`failOnRisky`. La suite predefinita è `unit` (`tests/Unit`, 17 file); la suite
`e2e` richiede `TELEGRAM_BOT_TOKEN`.

Verifica eseguita il 2026-11-11 con PHP 8.5.12 su Windows: `composer validate
--strict` valido; `composer phpstan` riporta «No errors»; `composer test`
riporta 528 test e 1767 asserzioni con 2 errori, 3 fallimenti e 2 test saltati.
I 5 casi che falliscono riguardano permessi di cartelle e file in sola lettura
(`CacheTest`, `LoggerTest`) e copie da file locali (`ApiClientTest`,
`FullCoverageTest`): sembrano dipendere da permessi POSIX e percorsi di Linux
(causa da confermare), dove gira la CI (esito verde su Linux da confermare). La
coverage al 100% non è stata misurata in questa revisione.

Note sulla struttura: la guida utente è nella cartella `wiki/` (pubblicata dal
workflow `wiki`); la documentazione tecnica è in `docs/`. I due insiemi vanno
tenuti coerenti (requisito 12).

## Architettura

Tre livelli (dettaglio in `docs/architecture.md`):

1. `Framework\Bot` (facciata), `Kernel`, `Router`, `MiddlewarePipeline`,
   `EventDispatcher`, runner `WebhookHandler` e `LongPolling`.
2. `Core\ApiClient` con un trait per area della API in `Methods\` e
   `Traits\HttpClientTrait` (codifica, mappatura errori, ripetizioni).
3. `Http\TransportInterface`, implementata da `CurlTransport`: è l'unico confine
   di I/O ed è sostituita da `Testing\FakeTransport` nei test.

Altre cartelle di `src/`: `CLI` (`bin/tgbot`), `Cache`, `Exceptions`, `Plugin`,
`Rate`, `Security`, `Session`, `Support`, `Testing`, `Types`, `Utilities`.

Flussi:

- Chiamata in uscita: un trait di metodo costruisce i parametri,
  `HttpClientTrait` li codifica (JSON o multipart se ci sono file), il transport
  invia la richiesta, la risposta è decodificata qualunque sia lo stato HTTP,
  gli errori diventano eccezioni tipizzate, il risultato è confrontato con il
  tipo dichiarato.
- Webhook: `handle()` controlla metodo, secret token (confronto a tempo
  costante) e dimensione del corpo, analizza l'aggiornamento, lo passa al
  `Kernel`, risponde 200 (o 500 se `acknowledgeHandlerErrors` è falso).
- Long polling: `poll()` chiama `getUpdates` con un offset, passa ogni
  aggiornamento al `Kernel`, rallenta con backoff in caso di errori di rete e
  429.
- Gestione dell'aggiornamento: eventi, poi middleware, poi la prima route
  corrispondente, poi lo stato della conversazione se ce n'è una attiva per
  utente e chat.

Dati:

- Aggiornamenti e risultati sono oggetti `stdClass`, come li invia Telegram;
  `Support\Value` e `Support\Payload` danno letture tipizzate.
- `Core\Config` è immutabile (readonly): token, secret token, URL base della
  API, obbligo di HTTPS, timeout, `RetryPolicy`, log di debug,
  `allowUnauthenticatedWebhook`, `maxWebhookBodyBytes`,
  `acknowledgeHandlerErrors`, `localFileRoot`.
- Lo stato (sessioni, conversazioni, limiti di frequenza) sta dietro
  `Cache\CacheInterface`: `ArrayCache` (memoria del processo) o `FileCache`
  (file leggibili solo dal proprietario, nessuna deserializzazione di oggetti).

Interfacce:

- API PHP pubblica: `Bot`, `ApiClient`, `Config`, `Keyboard`, `InlineKeyboard`,
  `Formatter`, `InputFile`, `RateLimiter`, `WebhookValidator`, `BotTester`
  (riferimento in `docs/api-reference.md`).
- Punti di estensione: `TransportInterface`, `CacheInterface`,
  `PluginInterface`, `BotPluginInterface`, middleware come callable.
- Variabili d'ambiente di esempi e Docker: `TELEGRAM_BOT_TOKEN`,
  `TELEGRAM_SECRET_TOKEN`, `TELEGRAM_BOT_USERNAME` (vedi `.env.example`).
- Rete: HTTPS in uscita verso il server della Bot API (predefinito
  `https://api.telegram.org`); richieste HTTPS in ingresso dal webhook di
  Telegram.
- CLI `bin/tgbot`: `webhook:info`, `webhook:set`, `webhook:delete`, `bot:info`,
  `bot:test`, `commands:list`, `commands:delete`.

## Requisiti funzionali

1. Ogni metodo e tipo di aggiornamento della Bot API 10.3 è presente, con nomi e
   tipi dei parametri uguali alla documentazione ufficiale; `call()` copre i
   metodi futuri. Accettazione: `tests/Unit/BotApiCoverageTest.php` passa contro
   `tools/bot-api.json`.
2. Invio di file (`InputFile`, album, sticker) e `downloadFile()`. Accettazione:
   `ApiClientTest` passa; i caricamenti passano solo da `InputFile` esplicito.
3. Routing di comandi (anche `/cmd@bot` e payload deep-link), pattern di testo,
   callback, inline query e qualsiasi tipo di aggiornamento. Accettazione:
   `BotTest` passa.
4. Pipeline di middleware, dispatcher di eventi, plugin, sessioni per utente,
   conversazioni a più passi. Accettazione: `BotTest`, `PluginManagerTest`,
   `CacheTest` passano.
5. Runner webhook (`handle()`) e long polling (`poll()`) con backoff.
   Accettazione: `LongPollingTest` e `BotTest` passano.
6. Eccezioni tipizzate, gestione 429 con `retry_after`, politica di ripetizione
   configurabile. Accettazione: `ApiClientTest` passa.
7. Helper di sicurezza: secret token, intervalli IP di Telegram, validazione
   `initData` delle Mini App, escaping, limite di frequenza, redazione dei log.
   Accettazione: `SecurityTest`, `LoggerTest` passano.
8. Strumenti di test senza rete: `BotTester`, `FakeUpdate`, `FakeTransport`.
   Accettazione: `TestingKitTest` passa.
9. CLI `bin/tgbot` con i comandi elencati. Accettazione: `ConsoleTest` passa.
10. Un webhook senza secret token è rifiutato (403) salvo
    `allowUnauthenticatedWebhook`. Accettazione: test in `BotTest` o
    `SecurityTest`.
11. Allineamento a nuove versioni della Bot API. Accettazione: il workflow
    `bot-api` segnala le differenze rispetto a `tools/bot-api.json`; `php
    tools/bot-api-spec.php > tools/bot-api.json` aggiorna lo schema e
    `BotApiCoverageTest` torna verde.
12. La documentazione cita solo funzioni, classi e comandi esistenti.
    Accettazione: `tests/Unit/DocumentationTest.php` passa.

## Requisiti non funzionali

- Sicurezza (vedi `SECURITY.md`): webhook senza segreto rifiutati, formato del
  token validato, HTTPS e verifica TLS di default, nessun redirect, upload solo
  da `InputFile`, segreti rimossi dai log, nessuna iniezione nei log, container
  in sola lettura senza capability e senza root, segreti solo da variabili
  d'ambiente. Scansione segreti con gitleaks (`.gitleaks.toml`).
- Prestazioni: nessuna dipendenza a runtime; uno stesso processo gestisce
  aggiornamenti in sequenza; più istanze richiedono una `CacheInterface`
  condivisa.
- Privacy: la libreria non salva dati personali oltre a sessioni e conversazioni
  nella cache scelta dall'utente.
- Qualità: coverage di riga 100% su `src/` (CI: ogni riga deve essere coperta),
  PHPStan livello 10, stile PER-CS 2.0, zero avvisi PHPUnit, SonarCloud A/A/A e
  hotspot A, dipendenze e azioni all'ultima versione (Dependabot).
- Compatibilità: i test con permessi di file assumono POSIX e falliscono su
  Windows; la suite si esegue su Linux, WSL o Docker.

## Roadmap

1. Misurare lo stato (test, PHPStan, coverage) e annotare l'esito.
2. Seguire ogni nuova release della Bot API (il test di copertura segnala le
   differenze).
3. Valutare classi tipizzate per aggiornamenti e risultati al posto di
   `stdClass`.
4. Backend di cache condivisi (Redis, database).
5. Rendere PHP 8.6 un target obbligatorio della CI quando sarà stabile.

## Fuori ambito

- Persistenza oltre `FileCache` (serve un'implementazione di `CacheInterface`
  dell'utente).
- Limiti di Telegram (frequenza, dimensione file): non sono imposti localmente,
  tranne `RateLimiter` per utente.
- Esecuzione dei test di permessi su Windows.

## Decisioni aperte per il proprietario

- Coverage Sonar: `bin/`, `examples/` e `tools/` sono esclusi da
  `sonar.coverage.exclusions`; mantenere l'esclusione o scrivere test.
- Passare agli oggetti tipizzati per gli aggiornamenti (rottura di
  compatibilità).
- Pubblicazione su Packagist e politica di versionamento (da confermare).

## Come verificare

```bash
composer validate --strict
composer install --no-interaction --no-progress
composer test          # PHPUnit, suite unit
composer phpstan       # livello 10 con regole strict
vendor/bin/php-cs-fixer check --diff   # stile (richiede php-cs-fixer installato)
vendor/bin/phpunit --coverage-clover coverage.xml   # richiede pcov o xdebug
php tools/coverage-check.php coverage.xml 100       # come in CI: fallisce sotto il 100%
```

CI: `Tests`, `Code Analysis`, `Lint`, `Security`, `SonarCloud Linux`. I test
end-to-end contro la API reale sono manuali (`docs/testing.md`).
