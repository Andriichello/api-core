# Custom filters

A filter class decides how a filter changes the query. Write one when a filter needs more than comparing a column of
the model: a column of a related model, several columns, or a condition that isn't a column at all. It still takes the
same operations, flags and query string syntax as the built-in filters.

## The classes

| Class                                   | Role                                                                                   |
|-----------------------------------------|----------------------------------------------------------------------------------------|
| `Core\Http\Filters\BaseFilter`          | The abstract base: the table, the operation, the flags and `apply()`.                  |
| `Core\Http\Filters\DefaultFilter`       | Applies the class of its operation, such as `EqFilter` for `eq`.                       |
| `Core\Http\Filters\OperationFilter`     | The abstract base of a filter of one operation, whose operation can't be changed.      |
| `Core\Http\Filters\DateFilter`          | Dates and timestamps (see [Date filters](02-date-filters.md)).                         |
| `Core\Http\Filters\Operations\*Filter`  | `EqFilter`, `InFilter`, `NullFilter`, `LikeFilter`, `ContainsFilter`, `GtFilter`, `GteFilter`, `LtFilter`, `LteFilter`. |

`BaseFilter` gives its subclasses:

| Member                                      | Description                                                                   |
|---------------------------------------------|-------------------------------------------------------------------------------|
| `__construct(?string $table = null)`        | The table, which unqualified columns are qualified with.                      |
| `$operation`, `setOperation()`              | The requested operation (`FilterOperation`).                                  |
| `$flags`, `setFlags()`                      | The requested flags (`FilterFlag[]`).                                         |
| `hasFlag($flag, ...$more)`                  | `true` when all the given flags are set.                                      |
| `hasNoFlag($flag, ...$more)`                | `true` when none of the given flags is set.                                   |
| `qualify($column)`                          | `invoices.total` for `total`; a column with a dot is kept as it is.           |
| `wrap($query, $column)`                     | The qualified column, quoted for raw SQL by the query's grammar.              |
| `compare($query, $column, $operator, $value, $boolean = 'and')` | A comparison, of the lowercased sides with `ci`.          |
| `apply($query, $column, $value, $boolean = 'and')` | Abstract: adds the filter's condition to the query.                    |

## How the repository calls a filter

For each requested filter, `CrudRepository` makes the filter's class with the table of the filter's entry, or the
model's table, sets the operation and the flags, and calls `apply()` with the column of the entry (or the filter's
name) and the value. An `InvalidArgumentException` thrown by `apply()` answers `422`, with its message under
`filter.{name}`, so a filter refuses what it can't handle by throwing one.

## A filter on a related model

This filter finds the invoices whose client matches, with any operation and flags, by applying a `DefaultFilter` to
the client's query:

```php
namespace App\Http\Filters;

use Core\Http\Filters\BaseFilter;
use Core\Http\Filters\DefaultFilter;
use Core\Queries\BaseQuery;

class ClientFilter extends BaseFilter
{
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        $query->has('client', '>=', 1, $boolean, function (BaseQuery $clients) use ($column, $value) {
            (new DefaultFilter($clients->getModel()->getTable()))
                ->setOperation($this->operation)
                ->setFlags($this->flags)
                ->apply($clients, $column, $value);
        });
    }
}
```

```php
protected array $allowedFilters = [
    'client_name' => [ClientFilter::class, 'name'],
];
```

`filter[client_name][ci][contains]=acme` then gives:

```sql
exists (select * from "clients" where "invoices"."client_id" = "clients"."id"
    and LOWER("clients"."name") LIKE LOWER('%acme%') ESCAPE '!')
```

The client's query is a `BaseQuery` when `Client` extends `BaseModel`.

## A filter of its own operations

A filter that takes some operations only checks `$this->operation` in `apply()` and throws for the others:

```php
use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use InvalidArgumentException;

class OverdueFilter extends BaseFilter
{
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        if ($this->operation !== FilterOperation::EQ) {
            throw new InvalidArgumentException("The 'overdue' filter takes true or false only.");
        }

        $isOverdue = filter_var($value, FILTER_VALIDATE_BOOLEAN) !== $this->hasFlag(FilterFlag::Not);

        $query->where($this->qualify('due_at'), $isOverdue ? '<' : '>=', now(), $boolean);
    }
}
```

Allowed as `'overdue' => OverdueFilter::class`, `filter[overdue]=1` lists the overdue invoices, and
`filter[overdue][gt]=1` answers `422` with that message.

`OperationFilter` is the base of the operation classes, which `DefaultFilter` picks: their `setOperation()` throws an
`InvalidArgumentException` for any other operation than their own. A filter allowed on requests checks the operation
in `apply()`, as above, so a wrong one answers `422`.

## Using the operation classes in code

The operation classes work on any `BaseQuery`, outside of requests too:

```php
use Core\Enums\FilterFlag;
use Core\Http\Filters\Operations\ContainsFilter;

(new ContainsFilter('clients'))
    ->setFlags([FilterFlag::CaseInsensitive])
    ->apply($query, 'name', 'acme'); // LOWER("clients"."name") LIKE LOWER('%acme%') ESCAPE '!'
```

## Related

- [Filters](00-filters.md)
- [Filter operations](01-filter-operations.md)
- [Date filters](02-date-filters.md)
- [Base query](../queries/00-base-query.md)
