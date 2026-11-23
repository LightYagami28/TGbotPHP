# Architettura

L'indice della documentazione è in [docs/architecture.md](docs/architecture.md),
il piano di lavoro in [ROADMAP.md](ROADMAP.md).

`TGbotPHP` è una libreria modulare orientata agli oggetti per le API Bot di
Telegram. La documentazione completa dei livelli è in
[docs/architecture.md](docs/architecture.md).

```text
                           +--------------------------+
                           |      Framework\Bot       |
                           | (facade, Kernel, Router) |
                           +------------+-------------+
                                        |
         +------------------------------+------------------------------+
         v                              v                              v
+-----------------------+    +--------------------------+    +---------------------+
| Core\ApiClient        |    | Security\WebhookValidator|    | Http\CurlTransport  |
| Methods\* (API)       |    | (secret, IP, MiniApp)    |    | (cURL, TLS, timeout)|
+-----------------------+    +--------------------------+    +---------------------+
         |
         v
+-----------------------+
| Http\TransportInterface
| (confine di I/O)      |
+-----------------------+
```

| Componente | File/Namespace | Ruolo |
| ---------- | -------------- | ----- |
| Facade | `src/Framework/Bot.php` (`TGbotPHP\Framework\Bot`) | Registra handler, espone `handle()` e `poll()`, estende `Core\ApiClient`. |
| Kernel e routing | `src/Framework/Kernel.php`, `Router.php`, `Concerns/` | Smista gli update, esegue middleware ed eventi, risolve comandi e pattern. |
| API client | `src/Core/ApiClient.php`, `src/Methods/*.php` | Metodi tipizzati delle API Bot e retry su 429. |
| Trasporto HTTP | `src/Http/TransportInterface.php`, `CurlTransport.php`, `src/Traits/HttpClientTrait.php` | I/O, codifica dei campi, upload `InputFile`, verifica TLS. |
| Sicurezza | `src/Security/WebhookValidator.php`, `MiniAppValidator.php`, `LogRedactor.php` | Secret token, IP di Telegram, firme HMAC/Ed25519 e redazione dei log. |
| Test | `src/Testing/BotTester.php`, `FakeTransport.php`, `FakeUpdate.php`, `tests/` | Test degli handler senza rete; suite PHPUnit con mock HTTP. |

## Confini

`Http\TransportInterface` è l'unico confine di I/O: i test lo sostituiscono con
`Testing\FakeTransport`. Le credenziali restano lato server nella
configurazione immutabile `Core\Config` e non vengono scritte nei log.
