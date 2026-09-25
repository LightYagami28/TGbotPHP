# Contributing

1. Fork the repository and create a branch.
2. Make your change, with tests.
3. Run the checks:

   ```bash
   composer install
   composer check   # PHPUnit + PHPStan level 10
   ```

4. Open a pull request describing what changed and why.

## Guidelines

- PSR-12 code style, `declare(strict_types=1)` in every file.
- PHPStan must pass at level 10 without baseline entries or `@phpstan-ignore`.
- New API methods follow [docs/development.md](docs/development.md#adding-an-api-method).
- User-facing changes go in [CHANGELOG.md](CHANGELOG.md).

Report security problems privately, as described in [SECURITY.md](SECURITY.md).
