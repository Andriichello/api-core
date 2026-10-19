# Running tests

How to run api-core's PHPUnit tests: on SQLite in memory by default, in parallel, and on MySQL. Run the tests of a
change before committing it, and the whole suite on both databases before a release.

## On SQLite

`phpunit.xml` runs the tests on SQLite in memory, with the `array` cache, session and mailer, the `sync` queue and
`BCRYPT_ROUNDS=4`, so they need no services.

```bash
php8.5 artisan test --compact
php8.5 artisan test --compact tests/Unit/Models/SequenceTest.php
php8.5 artisan test --compact --filter=testIndexReturnsAPageOfTheModelsInScope
```

## In parallel

```bash
XDEBUG_MODE=off php8.5 artisan test --parallel --processes=6
```

`composer test` runs the same after `php artisan config:clear`. Its test command calls `php` from `PATH`, so use the
command above, when that `php` is older than 8.5. `XDEBUG_MODE=off` keeps Xdebug from slowing the run down. A parallel
run takes a single path; to run several files, drop `--parallel`.

## On MySQL

Environment variables of the shell take precedence over the `<env>` entries of `phpunit.xml`, so a run picks MySQL
with:

```bash
DB_CONNECTION=mysql DB_DATABASE=api_core_test XDEBUG_MODE=off php8.5 artisan test --parallel --processes=6
```

- The host, port, user and password come from `.env`.
- Create the `api_core_test` database first. A parallel run connects to it and then creates and uses one database per
  process, `api_core_test_test_1` to `api_core_test_test_6`.
- **Never point the tests at a development database**, such as `api_core` of `.env.example`. `RefreshDatabase` runs
  `migrate:fresh` on it, which drops every table.
- With a cached config (`bootstrap/cache/config.php`), Laravel ignores the environment, so the tests would use the
  cached database connection. Run `php8.5 artisan config:clear` first.
- `SequenceLockTest` runs only on MySQL; SQLite skips it, as it ignores row locks.

The suite should pass on both databases. MySQL differs from SQLite in ways, which tests must allow for: it quotes
identifiers with backticks, reorders the keys of JSON columns, and commits the open transaction on `CREATE TABLE`.
[Writing tests](02-writing-tests.md) has the helpers for each.

## Related

- [Development setup](00-setup.md)
- [Writing tests](02-writing-tests.md)
- [Code quality](03-code-quality.md)
- [Sequences](../models/04-sequences.md)
