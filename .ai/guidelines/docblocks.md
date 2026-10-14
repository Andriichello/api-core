# Class DocBlocks

Every class, interface, trait, and enum has a DocBlock directly above its declaration:

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

- Summary line: `{Kind} {ShortName}.`, where the kind matches the declaration (`Class`, `Interface`, `Trait`, `Enum`).
- Type tags (`@template*`, `@extends`, `@implements`, `@mixin`, `@phpstan-*`, `@property*`, `@method`, `@deprecated`, `@see`) come next, followed by a blank line. Omit the section when empty.
- `@author` (copied from sibling classes) is followed by `@package`, which holds the full namespace and never a file path.
- Tool directives (`@SuppressWarnings`, `@psalm-suppress`, `@phpstan-ignore*`, `@codeCoverageIgnore`, `@internal`) go directly after `@package` with no blank line, never above `@author`. Quote PHPMD rule names (`@SuppressWarnings("PHPMD.RuleName")`), because PHPStan cannot parse the unquoted dotted form.
- When editing a class whose DocBlock is missing or misordered, fix it.
- For Eloquent models, generate the `@property` tags with the `model-property-docblocks` skill.
