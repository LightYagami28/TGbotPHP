# Security Policy

## Segnalare una vulnerabilità

Non aprire una issue pubblica. Usa GitHub Security Advisories:
<https://github.com/LightYagami28/TGbotPHP/security/advisories/new>
oppure scrivi a [ceo@retechrevive.it](mailto:ceo@retechrevive.it).
Non includere token, segreti o dati personali nella segnalazione.

## Versioni supportate

Solo l'ultima versione pubblicata riceve correzioni di sicurezza. La dipendenza
minima è PHP 8.5.

## Protezioni integrate

| Protezione | Dove |
|---|---|
| Webhook senza secret rifiutati (403) per impostazione predefinita | `Bot::handle()` |
| Secret token confrontato a tempo costante | `WebhookValidator::validate()` |
| Validazione del formato del token (no path injection negli URL) | `Config` |
| Solo URL API e webhook HTTPS | `Config`, `setWebhook()` |
| Verifica TLS di certificato e host | `CurlTransport` |
| Upload solo tramite `InputFile` esplicito | metodi media |
| Controllo degli IP dei webhook | `WebhookValidator::isTelegramIp()` |
| Verifica HMAC e scadenza dei Mini App | `WebhookValidator::validateWebAppData()` |
| Verifica Ed25519 dei Mini App di terze parti | `WebhookValidator::validateWebAppSignature()` |
| Escape HTML e MarkdownV2 | `Formatter` |
| Nessuna deserializzazione di oggetti dalla cache | `FileCache` |
| Prevenzione del log injection e redazione dei segreti | `Logger`, `LogRedactor` |
| Rate limiting per utente | `RateLimiter::middleware()` |
| Configurazione immutabile | `Config` (readonly) |
| Nessun redirect, solo HTTPS (HTTP solo per loopback di test) | `CurlTransport` |

## Modello di sicurezza

- **Least privilege**: il token bot resta lato server; il secret webhook è
  obbligatorio salvo esplicita disattivazione per sviluppo locale.
- **Fail closed**: input non validi, firme errate e `auth_date` assente o futuro
  vengono rifiutati.
- **Output encoding**: i testi inviati a Telegram vengono escapati in base al `parse_mode`.
- **Errori senza leak**: i messaggi di errore non espongono token; `LogRedactor`
  rimuove i segreti dai log.
- **Catena di fornitura**: `composer.lock` contiene checksum. Le GitHub Action
  sono pinnate tramite impronta crittografica a 256 bit e `composer audit` gira
  in CI. Ogni build pubblica un Software Bill of Materials (SBOM) in formato
  Software Package Data Exchange (SPDX) e CycloneDX. Pubblica anche
  un'attestazione Supply-chain Levels for Software Artifacts (SLSA) firmata con
  Sigstore. Le immagini Docker pinnano le basi per digest.
- **Verifica degli artefatti**: scaricare archivio, checksum e SBOM dalla
  release e verificare l'attestazione con
  `gh attestation verify <file> --repo LightYagami28/TGbotPHP`.
- **Conformità**: le misure seguono OWASP Top 10 2025 (A01, A02, A03, A04, A05,
  A06, A09, A10), NIST CSF 2.0 e SSDF SP 800-218. Le sezioni LLM/GenAI e
  Supabase non sono applicabili: la libreria non usa modelli generativi né
  database.

## Glossario

- **PHP**: PHP: Hypertext Preprocessor.
- **OWASP**: Open Web Application Security Project.
- **NIST**: National Institute of Standards and Technology.
