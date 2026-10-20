# Development setup

How to set up a working copy of api-core itself, to change it and run its tests and checks. Applications, which only
use api-core, need [Installation](../getting-started/02-installation.md) instead.

## Requirements

- PHP 8.5 CLI with `pdo_sqlite` (the tests' default database), `fileinfo`, `mbstring` and the other extensions of
  Laravel; `pdo_mysql` and a MySQL 8.0.13+ server to run the tests on MySQL too.
- Composer 2 and Git.

## PHP 8.5

`composer.json` requires PHP `^8.5`, so Composer's platform check refuses an older PHP. When `php` on your `PATH` is
older, call the 8.5 binary explicitly:

```bash
php8.5 artisan test --compact
php8.5 vendor/bin/pint --dirty
php8.5 "$(command -v composer)" install
```

The Git hooks run `vendor/bin/grumphp` with plain `php`, so `php` must be PHP 8.5 when you commit. Without switching
the system's default, put a `php` link to `php8.5` first on `PATH` for the commit:

```bash
mkdir -p ~/.local/php85-bin && ln -sf "$(command -v php8.5)" ~/.local/php85-bin/php
PATH="$HOME/.local/php85-bin:$PATH" git commit -m "fix: …"
```

## Installing

```bash
git clone https://github.com/Andriichello/api-core.git
cd api-core
composer setup
```

The `setup` script runs `composer install`, copies `.env.example` to `.env` (unless it exists), generates the app key
and runs the migrations. Installing also puts GrumPHP's `pre-commit` and `commit-msg` hooks into `.git/hooks`.

`.env.example` points at a MySQL database `api_core` on `127.0.0.1`. Create it before `composer setup`, or switch the
copy to SQLite: set `DB_CONNECTION=sqlite`, remove the other `DB_*` lines, so Laravel uses `database/database.sqlite`,
and create that file with `touch database/database.sqlite`. This database serves `artisan` and `tinker` only; the
tests use their own (see [Running tests](01-running-tests.md)).

When `php` is older than 8.5, the script's nested `composer install` fails the platform check, so run its steps by
hand:

```bash
php8.5 "$(command -v composer)" install
cp -n .env.example .env
php8.5 artisan key:generate
php8.5 artisan migrate
```

## The repository

api-core is a library, but its repository is also a small Laravel application (`artisan`, `bootstrap/app.php`,
`bootstrap/providers.php`), so its tests, Artisan commands and Tinker run against it.

| Path                    | Contents                                                                                   |
|-------------------------|--------------------------------------------------------------------------------------------|
| `app/`                  | the package's code, namespace `Core\`                                                      |
| `config/`               | `api-core.php` and `pagination.php`, which apps get; `auth.php` for the repository's own app |
| `database/`             | migrations, factories (`Core\Database\Factories`) and seeders (`Core\Database\Seeders`)    |
| `tests/`                | `Unit` and `Feature` tests, namespace `Core\Tests`                                         |
| `bootstrap/providers.php` | registers the three providers for the repository's own app                               |
| `.ai/guidelines/`       | the project's rules of commits, docblocks and documents, included in `CLAUDE.md`           |
| `.ai/skills/`           | the project's skills, such as `writing-docs` with its `check-docs.php`                     |
| `resources/boost/`      | the guideline and `api-core-development` skill, which Boost installs into applications     |

`.gitattributes` keeps the development files (tests, tool configs, `CLAUDE.md`, `.ai`) out of the archives, which
Composer installs into applications.

## Related

- [Running tests](01-running-tests.md)
- [Writing tests](02-writing-tests.md)
- [Code quality](03-code-quality.md)
- [Conventions](04-conventions.md)
- [Overview](../getting-started/00-overview.md)
