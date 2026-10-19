# Code quality

The checks every commit of api-core passes: a GrumPHP pre-commit hook with phpcs, phpmd, PHPStan, a PHP lint and
Composer's validation, and Pint for formatting, which you run yourself. Run them before committing, so the hook only
confirms.

## The pre-commit hook

`grumphp.yml` configures the hook, which GrumPHP's Composer plugin installs into `.git/hooks`:

| Task      | Config         | Checks                                                                          |
|-----------|----------------|---------------------------------------------------------------------------------|
| `phpcs`   | `phpcs.xml`    | PSR-12 style, with lines of at most 120 characters                              |
| `phpmd`   | `phpmd.xml`    | mess detection, with the baseline `phpmd.baseline.xml`                          |
| `phpstan` | `phpstan.neon` | static analysis at level 5, with the baseline `phpstan-baseline.neon`; skips `bootstrap/` and `database/migrations/` |
| `phplint` | –              | PHP syntax                                                                      |
| `composer`| –              | `composer.json` and the lock file                                               |

The tasks run in parallel and stop at the first failure. The hook runs with plain `php`, which must be PHP 8.5 (see
[Development setup](00-setup.md)). What to know about it:

- **It checks the staged files only.** A baseline entry, which a change makes unnecessary, is reported only by a full
  `vendor/bin/phpstan analyse`, so run it before committing. phpmd, seeing one file without the interface it
  implements, flags the unused parameters of the implementation, such as `$params` of a query's `index()`; suppress
  that on the method (`@SuppressWarnings("PHPMD.UnusedFormalParameter")`).
- **It stashes unstaged changes** while it runs (`ignore_unstaged_changes`). A file with both staged and unstaged
  changes can conflict, when the stash is popped back: the commit is aborted, conflict markers land in the file and a
  GrumPHP stash stays behind. Don't commit partially staged files, and check the exit code, `git log` and
  `git stash list` after every commit.
- **Dry-run it** on the staged files with `.git/hooks/pre-commit`. `vendor/bin/grumphp run` checks only files, which
  Git already tracks, so it skips new ones.

## Running the tools

```bash
php8.5 vendor/bin/phpcs
php8.5 vendor/bin/phpcs app/Models/User.php
php8.5 vendor/bin/phpmd app,config,database,tests text phpmd.xml
php8.5 vendor/bin/phpstan analyse
php8.5 vendor/bin/pint --dirty
```

Without paths, phpcs checks `app`, `config` and `tests`.

## phpcs

`phpcs.xml` applies PSR-12 to `app`, `config` and `tests`, without migrations and `docs`, and turns off three sniffs,
which conflict with Pint's formatting. Pint writes an empty constructor with promoted properties as `) {}`; put each
parameter on its own line, so phpcs accepts it:

```php
public function __construct(
    protected AccessTokenRepository $tokens = new AccessTokenRepository
) {}
```

## phpmd

`phpmd.xml` uses the `codesize`, `cleancode`, `controversial`, `design`, `naming` and `unusedcode` rule sets, with some
rules left out (the complexity and size limits, `StaticAccess`, `NumberOfChildren`). In practice:

- No `else`: return early or split the method.
- No boolean flag arguments, short variable names (but `$e`, `$ci`, `$id`, `$to`) or short method names (but `up()`,
  `me()`, `id()`).
- `CouplingBetweenObjects` and `TooManyFields` hit providers, controllers and tests with many fixtures; suppress them
  in the class docblock, after `@package`.

Write suppressions in the quoted form, `@SuppressWarnings("PHPMD.CouplingBetweenObjects")`, since PHPStan can't parse
the unquoted one. phpmd reads `phpmd.baseline.xml` next to `phpmd.xml` by itself; `--update-baseline` drops its
fixed entries. Fix or suppress new violations rather than adding them to the baseline.

## PHPStan

`phpstan.neon` analyses `app`, `tests`, `config` and `database` (without migrations) at level 5. Entries of
`phpstan-baseline.neon`, which no longer match, are errors, so delete an entry, once the code it covers is fixed.
PHPStan types forwarded calls of relations and builders loosely: build a relation in a variable first, or declare the
method on `BaseQuery` with `@method $this …`, as it does for `lockForUpdate()`, `limit()` and `offset()`.

## Pint

`pint.json` takes Laravel's preset and keeps the house style: full and left-aligned PHPDoc tags, `@package` tags, `!$x`
without a space, ` . ` around concatenation, one trait per `use`, camel case test methods, and the `{` of an anonymous
class on the next line. The hook doesn't run Pint, so run `vendor/bin/pint --dirty` before committing. It formats the
whole of every changed file, so check `git diff` for fixes outside your lines.

## Related

- [Development setup](00-setup.md)
- [Conventions](04-conventions.md)
- [Running tests](01-running-tests.md)
