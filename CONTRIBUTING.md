# Contributing

1. Fai un fork del repository e crea un branch dedicato.
2. Applica **una sola modifica coerente** per pull request: un fix, una
   funzione, una checklist o una configurazione collegata. Non mescolare
   riscritture, fix CI, aggiornamenti di dipendenze e documentazione non
   correlata.
3. Aggiungi i test per ogni modifica comportamentale.
4. Esegui le verifiche:

   ```bash
   composer install
   composer check                   # PHPUnit + PHPStan livello 10
   php-cs-fixer check --diff        # stile PER-CS 2.0
   composer audit                   # vulnerabilità delle dipendenze
   ```

5. Apri una pull request con titolo breve e concreto e una descrizione che
   indichi cosa cambia e come è stato verificato.

## Linee guida

- PHP 8.5 o successivo, stile PER-CS 2.0, `declare(strict_types=1)` in ogni file.
- PHPStan deve passare al livello 10 senza baseline né `@phpstan-ignore`.
- Zero errori, zero warning, zero soppressioni: correggi sempre la causa.
- Non usare catch vuoti, fallback silenziosi o valori predefiniti che mascherino
  errori.
- Nessun segreto nei file o nei log; nessuna traccia di generazione automatica e
  nessun `Co-authored-by`.
I nuovi metodi API seguono [docs/development.md](docs/development.md#adding-an-api-method).
Le modifiche visibili all'utente vanno in [CHANGELOG.md](CHANGELOG.md)
- e nella guida quando cambiano
  l'uso della libreria.
Il wiki si modifica in [`wiki/`](wiki),
- non su GitHub: il workflow `Wiki` lo pubblica
  quando arriva su `main`. `DocumentationTest` verifica che gli esempi usino
  metodi e parametri esistenti.
