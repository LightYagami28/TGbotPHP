---
title: "Roadmap"
description: "Stato attuale, obiettivi e prossimi passi del progetto."
---

## Roadmap

## Stato attuale

TGbotPHP copre la Telegram Bot API 10.4 con 193 metodi e wrapper dedicati, con
test di conformità. La suite PHPUnit esegue 543 test con 1822 asserzioni, tutti
superati (4 test saltati in ambienti senza estensioni opzionali). PHPStan
livello 10 con `phpstan-strict-rules` e `phpstan-phpunit` termina a zero errori;
`composer audit` non segnala vulnerabilità.

Le protezioni già presenti e verificabili nel codice sono:

- secret token webhook obbligatorio e confrontato a tempo costante;
- validazione del formato del token e limitazione degli URL API a HTTPS;
- verifica TLS e disattivazione dei redirect in `CurlTransport`;
- caricamento di file locali solo tramite `InputFile` esplicito;
- controllo degli IP dei webhook e verifica HMAC/Ed25519 dei Mini App;
- redazione dei segreti nei log e prevenzione del log injection;
- rate limiting per utente e configurazione immutabile.

## Obiettivi

- Mantenere 0 errori e 0 warning in PHPUnit, PHPStan e nelle analisi di
sicurezza.
- Mantenere `composer.lock` con checksum e zero dipendenze vulnerabili o
deprecate.
- Mantenere tutte le GitHub Action pinnate a SHA-256, aggiornate tramite
Dependabot.
- Tenere la documentazione (`docs/` e `wiki/`) allineata alle API effettive,
verificata da `DocumentationTest`.

## Prossimi passi

1. Completare la riscrittura dei sorgenti PHP con validazione esplicita degli
input, errori tipizzati e nessuna soppressione.
2. Aggiungere header HTTP sicuri (CSP, HSTS, `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`) negli esempi di deployment e nel manifest
Kubernetes.
3. Eliminare i 4 test saltati rendendo disponibili le estensioni opzionali
nell'ambiente CI.
4. Documentare le migrazioni alle nuove major delle dipendenze di sviluppo nel
`CHANGELOG.md`.
