# Domande frequenti

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
