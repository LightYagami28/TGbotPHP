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

- PHP 8.5 or later. PER-CS 2.0 code style (`php-cs-fixer fix`), `declare(strict_types=1)` in every file.
- PHPStan must pass at level 10 without baseline entries or `@phpstan-ignore`.
- New API methods follow [docs/development.md](docs/development.md#adding-an-api-method).
- User-facing changes go in [CHANGELOG.md](CHANGELOG.md), and in the guide when they change how the library is used.
- The wiki is edited in [`wiki/`](wiki), not on GitHub: the `Wiki` workflow publishes it when it reaches `main`, replacing edits made on GitHub. `DocumentationTest` checks that its PHP examples use existing methods and parameters, and that its links work.

## Documentation

The documentation is published in three places, from the same Markdown:

| Place | Source | Notes |
| --- | --- | --- |
| [GitHub Wiki](https://github.com/LightYagami28/TGbotPHP/wiki) | `wiki/` | Published by the `Wiki` workflow, without the frontmatter |
| Docs7 site | `docs.json` | The navigation lists every page of `docs/` and `wiki/` |
| Context7 | `context7.json` | The library indexed for the coding agents, with the rules of `context7.json` |

The pages of `docs/` and `wiki/` start with a frontmatter that titles and
describes them:

```yaml
---
title: "Getting started"
description: "Create a bot, install the library and run your first bot."
icon: rocket
---
```

The title replaces the level 1 heading of the page, so the body starts at the
level 2 headings. GitHub shows the frontmatter as a plain block, so the root
files of the repository keep their first heading and no frontmatter.

Report security problems privately, as described in [SECURITY.md](SECURITY.md).
