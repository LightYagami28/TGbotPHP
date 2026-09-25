# Contributing

1. Fork the repository and create a branch.
2. Make your change, with tests.
3. Run the checks:

   ```bash
   composer install
   composer check                # PHPUnit + PHPStan level 10
   php-cs-fixer check --diff     # code style (PER-CS 2.0)
   ```

4. Open a pull request describing what changed and why.

## Guidelines

- PER-CS 2.0 code style (`php-cs-fixer fix`), `declare(strict_types=1)` in every file.
- PHPStan must pass at level 10 without baseline entries or `@phpstan-ignore`.
- New API methods follow [docs/development.md](docs/development.md#adding-an-api-method).
- User-facing changes go in [CHANGELOG.md](CHANGELOG.md), and in the guide when they change how the library is used.
- The wiki is edited in [`wiki/`](wiki), not on GitHub: the `Wiki` workflow publishes it when it reaches `main`, replacing edits made on GitHub. `DocumentationTest` checks that its PHP examples use existing methods and parameters, and that its links work.

Report security problems privately, as described in [SECURITY.md](SECURITY.md).
