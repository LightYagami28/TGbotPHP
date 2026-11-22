---
titolo: Revisione finding Codacy
aggiornato: 2026-11-22
---

# Revisione finding Codacy — TGbotPHP

## Metodo

Rilevazione del 2026-11-22 tramite Codacy API v3:

- `POST .../issues/overview`
- `POST .../issues/search` con corpo
  `{"levels":["Error","High","Warning","Info"],"pageSize":100}`.

Gli endpoint sono sotto `/api/v3/analysis/organizations/gh/LightYagami28/`
nella sezione `repositories/TGbotPHP`.
La paginazione della search è **cursor-based** (`?cursor=N`): il campo `page`
nel corpo viene ignorato.
Somma dei 3 cursor: 277 issue, tutte `belowThreshold`.

## Conteggi prima

| Livello | Totale |
| --- | --- |
| Error | 9 |
| High | 39 |
| Warning | 16 |
| Info | 213 |
| **Totale** | **277** |

## Falsi positivi dimostrati — Error

| Pattern | N | File/riga | Evidenza |
| --- | --- | --- | --- |
| `Semgrep_codacy.yaml.security.hard-coded-tokens` | 2 | `.github/workflows/release.yml:48`, `.github/workflows/security.yml:42` | La riga segnalata è `id-token: write` nel blocco `permissions:` del job. È la sintassi GitHub Actions per concedere il permesso OIDC; non è un token. I segreti nel workflow sono solo riferimenti `${{ ... }}` (es. `GH_TOKEN: ${{ github.token }}` a `release.yml:120`). |
| `Semgrep_php.lang.security.injection.echoed-request.echoed-request` | 1 | `tests/Support/Server/router.php:21` | L'unico echo che usa dati della richiesta è `echo json_encode([...])`, con `header('Content-Type: application/json')` impostato all'inizio del file. `json_encode` escaped i caratteri HTML; non c'è echo grezzo di `$_POST`/`$_SERVER`. È inoltre un server built-in di test, non un endpoint di produzione. |
| `Semgrep_php_exec_rule-exec-use`, `Semgrep_php.lang.security.exec-use.exec-use` | 4 | `tests/Unit/CurlTransportTest.php:35`, `tests/Unit/CacheTest.php:129` | Sono chiamate `proc_open([PHP_BINARY, '-S'|'-r', ...], ...)` con **forma ad array**: non viene invocata alcuna shell, l'eseguibile è la costante `PHP_BINARY` e gli argomenti sono generati dal test (porta locale, path di `vendor/autoload.php`, directory temporanea). Nessun input utente raggiunge il comando. Sono necessarie per avviare il server HTTP locale e i processi di concorrenza dei test. |
| `Lizard_ccn-critical` | 1 | `tests/Unit/DocumentationTest.php:115` | Il messaggio è `Method ,::check has a cyclomatic complexity of 18`: il nome della classe è una virgola, segno che il parser di Lizard ha frainteso il file. La complessità non è attribuibile a un metodo reale. |
| `Lizard_file-nloc-critical` | 1 | `composer.lock:1` | File generato da Composer (2096 righe), non codice sorgente scritto o manutenibile nel repo. |

## Falsi positivi dimostrati — High

| Pattern | N | Evidenza |
| --- | --- | --- |
| `Semgrep_php.lang.security.unlink-use.unlink-use` | 27 | Tutte in `tests/**` (es. `tests/Unit/CacheTest.php:39,448`, `tests/Unit/FullCoverageTest.php:145,515`, `tests/E2E/MessagingTest.php:145`). Le variabili rimosse (`$file`, `$directory`, `$path`, `$destination`, `$log`) sono path di file temporanei creati dai test o dalla fixture `self::$directory`, non input di rete. Nessuna richiesta HTTP o parametro utente raggiunge `unlink()`. |
| `Semgrep_yaml.semgrep.duplicate-id.duplicate-id` | 11 | `k8s/ingress.yaml` non contiene **alcun campo `id:`**. Le segnalazioni (righe 16-26) cadono sul blocco `rules[].http.paths[]`: il rule interpreta chiavi YAML ripetute come `id`. |
| `Agentlinter_security_no-pii-exposure` | 1 | `SECURITY.md:5` contiene `ceo@retechrevive.it` come indirizzo di segnalazione pubblica delle vulnerabilità, scelto deliberatamente perché raggiungibile da chi segnala un bug. Non è un dato personale esposto per errore. |

## Da verificare / correggere nel codice (lotti successivi)

| Pattern | N | Natura |
| --- | --- | --- |
| `Lizard_ccn-medium`, `Lizard_nloc-medium`, `Lizard_file-nloc-medium` | 10 | Complessità e lunghezza di metodi/file: parte reale, da ridurre con refactoring mirato. |
| `markdownlint_*` | 76 | Formattazione documentazione (lunghezza righe, tabelle, intestazioni): correzioni meccaniche. |
| `PHPCS_*` | 32 | Formattazione PSR: correzioni meccaniche. |
| `Lizard_nloc-minor`, `Lizard_ccn-minor`, `Lizard_file-nloc-minor` | 103 | Soglia_info su metodi di test: valutare caso per caso. |
| Altri pattern Warning/Info | 16 | Dockerfile, k8s, acronimi: da valutare singolarmente. |

Nessuna soppressione, ignored path, baseline o abbassamento di soglia è stato
introdotto.
