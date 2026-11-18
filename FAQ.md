---
title: "FAQ"
description: "System requirements, tests, static analysis and token redaction in the logs."
---

# Domande frequenti

> Per le domande più comuni sul progetto consulta la [FAQ del sito](wiki/FAQ.md).

## Quali sono i requisiti di sistema?
- PHP 8.3 o successivo.
- Estensioni PHP: `curl`, `json`, `mbstring`, `openssl`.

## Come si eseguono i test e l'analisi statica?
```bash
composer test      # Esegue PHPUnit con copertura
composer phpstan   # Esegue analisi statica livello 8
composer phpcs     # Verifica conformità standard PSR-12
```

## Come vengono protetti i token nei log?
La libreria include la classe `LogRedactor` che anonimizza automaticamente i token Telegram e i dati sensibili prima di qualsiasi output.
