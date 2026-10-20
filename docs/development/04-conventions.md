# Conventions

The conventions of api-core's commits, docblocks, code and documents. The project's own rules live in
`.ai/guidelines/` (`commits.md`, `docblocks.md`, `docs.md`), which Laravel Boost includes in `CLAUDE.md` along with its
Laravel guidelines, and its skills in `.ai/skills/`; this page sums them up. After changing either, run
`php8.5 artisan boost:update`, which regenerates `CLAUDE.md` and links the skills into `.claude/skills/`.

## Commits

- One line, prefixed with its type: `fix:`, `feat:`, `update:`, `docs:`, `style:`, `test:` or `refactor:`.
- No `Co-Authored-By` or other trailers.
- Work is committed on `dev`, the branch applications install as `dev-dev`.

```text
fix: refuse audit log events longer than their column on any database, rather than only on MySQL
test: make the tests pass on MySQL, whose SQL quoting, JSON columns and DDL commits differ from SQLite's
```

## Class docblocks

Every class, interface, trait and enum has a docblock right above its declaration:

```php
/**
 * Trait AllowsFilters.
 *
 * @mixin CrudRepository
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Traits
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
```

1. The summary line is `{Kind} {ShortName}.`, where the kind matches the declaration: `Class`, `Interface`, `Trait`
   or `Enum`. A description of what it does may follow.
2. Type tags come next (`@template*`, `@extends`, `@implements`, `@mixin`, `@phpstan-*`, `@property*`, `@method`,
   `@deprecated`, `@see`), followed by a blank line. Leave the section out, when it is empty.
3. `@author`, copied from the sibling classes, and then `@package` with the full namespace, never a file path.
4. Tool directives (`@SuppressWarnings`, `@psalm-suppress`, `@phpstan-ignore*`, `@codeCoverageIgnore`, `@internal`)
   directly after `@package`, with no blank line, and never above `@author`. PHPMD rule names are quoted.

When you edit a class, whose docblock is missing or out of order, fix it.

## Model `@property` tags

A model's docblock lists its attributes and relations as `@property` tags, in groups separated by a blank line:

1. The table's columns in the schema's order, with `created_at`, `updated_at` and `deleted_at` last. The cast decides
   the type (a `decimal:2` cast is a `string`, dates are `Carbon`), and a nullable column adds `|null`.
2. Accessors and appended attributes, alphabetically.
3. Relations, in the order of their methods: `Related` or `Related|null` for one, `Collection<int, Related>` for many.

A model with its own query class also declares `@method static XQuery query()`. Update the tags, whenever a migration,
a cast, an accessor or a relation changes.

## Member docblocks

Methods, properties and constants have docblocks too: a summary, then `@param`, `@return` and `@throws` for methods
and `@var` for properties. In tests, so do test methods (`Test that ….` and `@return void`), helpers, `setUp()`,
`tearDown()` and properties. Prefer docblocks to inline comments, which are kept for logic that isn't obvious.

## PHP

- Curly braces around every control structure, even of one line.
- Constructor property promotion; no empty constructors without parameters, unless they are private.
- Explicit types of every parameter and return value.
- Enum cases in TitleCase (`SuperAdmin`, `CaseInsensitive`). `FilterOperation` is the exception, with upper-case
  cases (`EQ`, `GTE`), which code using it relies on.
- Array shapes in PHPDoc, such as `array{field: string, value: mixed}`, where they help.
- No `else` (phpmd forbids it): return early instead.
- Descriptive names, such as `isRegisteredForDiscounts`, not `discount()`.

## Structure

Follow the sibling files of what you add: their directory, naming and shape, such as a query class next to its model's
other queries in `app/Queries` and a test under the mirrored path in `tests/`. Reuse the existing base classes and
traits before writing new ones, and keep to the existing directories.

## Documents

- A change of behavior, config, routes or of a public class or method updates the documents that describe it, in the
  same commit. What applications meet when upgrading also goes into [Upgrading](../getting-started/08-upgrading.md).
- The `writing-docs` skill (`.ai/skills/writing-docs/SKILL.md`) holds the layout and style of the documents. Its
  script checks names and numbering, the index, links and anchors, line lengths and the classes, methods and config
  keys, which the documents cite:

  ```bash
  php8.5 .ai/skills/writing-docs/scripts/check-docs.php
  ```

- The guideline and the skill in `resources/boost/` cite documents by their installed path,
  `vendor/andriichello/api-core/docs/…`. `GuidelinesAndSkillsTest` checks that they exist.

## Related

- [Code quality](03-code-quality.md)
- [Writing tests](02-writing-tests.md)
- [Development setup](00-setup.md)
