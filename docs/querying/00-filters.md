# Filters

The `filter` query parameter narrows the lists of a `CrudController` (and any query that a `CrudRepository` builds)
to the records that match. The repository checks every filter against the ones it allows, and answers `422` for any
other, so a client can't filter by a column that it shouldn't see.

## Syntax

A filter names a field, optionally flags, an operation and a value:

```http
GET /api/invoices?filter[status][eq]=draft
GET /api/invoices?filter[status][not][in]=paid,void
GET /api/invoices?filter[number][ci][contains]=inv-00
```

| Query                                              | SQL                                                                    |
|----------------------------------------------------|------------------------------------------------------------------------|
| `filter[status]=draft`                             | `"invoices"."status" = 'draft'`                                        |
| `filter[status][eq]=draft`                         | `"invoices"."status" = 'draft'`                                        |
| `filter[status][not]=draft`                        | `"invoices"."status" != 'draft'`                                       |
| `filter[status][ci]=DRAFT`                         | `LOWER("invoices"."status") = LOWER('DRAFT')`                          |
| `filter[id][gt]=5&filter[id][lt]=10`               | `"invoices"."id" > '5' and "invoices"."id" < '10'`                     |
| `filter[status][eq]=draft&filter[client_id][eq]=3` | `"invoices"."status" = 'draft' and "invoices"."client_id" = '3'`       |
| `filter[number][0][like]=INV%25&filter[number][1][like]=%251` | `"invoices"."number" LIKE 'INV%' and "invoices"."number" LIKE '%1'` |
| `filter[status][eq]=`                              | Nothing: an empty value is skipped.                                    |

Without an operation, a filter is `eq`; the operations and the flags are described in
[Filter operations](01-filter-operations.md). All the filters of a request apply together (`and`): several
operations on one field, and a list of filters on one field (`filter[number][0][...]`, `filter[number][1][...]`).
An empty value filters nothing, except for the `null` operation, for which it means "is null".

The columns are qualified with the model's table, and the values are bound, as strings, as they come in the query
string. `%` is written `%25` in a URL, so `like` patterns are encoded.

## Allowed filters

A repository allows filters by two properties of `AllowsFilters`, which `CrudRepository` uses:

```php
class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected bool $allowAllFilters = true;

    protected array $allowedFilters = [
        'client' => [DefaultFilter::class, 'client_id'],
        'paid_on' => DateFilter::class,
    ];
}
```

With `$allowAllFilters` on (the default), the model's queryable columns are allowed, as well as the listed filters:
the primary key, the fillable columns and the timestamps, without the hidden ones (`listOfQueryableColumns()`, see
[Base model](../models/00-base-model.md)). With it off, only the listed filters are allowed, and an empty list allows
none.

A hidden column, such as a password or a token, is never allowed unless it is listed: a filter on it would reveal its
value, one request at a time, without the value ever being returned. Columns that aren't fillable, such as
`deleted_at`, are allowed only when they are listed.

| Entry                                       | Filter        | Column           | Class                     |
|---------------------------------------------|---------------|------------------|---------------------------|
| `'paid_at'`                                 | `paid_at`     | `paid_at`        | Chosen by the column      |
| `'paid_on' => DateFilter::class`            | `paid_on`     | `paid_on`        | `DateFilter`              |
| `'client' => [DefaultFilter::class, 'client_id']` | `client` | `client_id`     | `DefaultFilter`           |
| `'client_name' => [null, 'name', 'clients']` | `client_name` | `clients.name`  | Chosen by the column      |

A class chosen by the column is `DateFilter` for the model's timestamps and its columns cast to `date`, `datetime`,
`timestamp` or their `immutable_` forms (see [Date filters](02-date-filters.md)), and `DefaultFilter` for any other
one, a column of another table included. A filter on another table's column needs that table joined by the query.
Custom classes are described in [Custom filters](03-custom-filters.md).

## Errors

Each error answers `422`, keyed by `filter.{name}`, or by `filter` when the parameter itself is wrong:

```json
{
    "message": "The 'password' filter is not allowed. Allowed filters: client, paid_on, id, number, status, client_id, created_at, updated_at.",
    "errors": {
        "filter.password": [
            "The 'password' filter is not allowed. Allowed filters: client, paid_on, id, number, status, client_id, created_at, updated_at."
        ]
    }
}
```

| Request                     | Key               | Message                                                                     |
|-----------------------------|-------------------|-----------------------------------------------------------------------------|
| `filter[secret][eq]=x`      | `filter.secret`   | `The 'secret' filter is not allowed. Allowed filters: …` (the list)          |
| Same, with none allowed     | `filter.secret`   | `The 'secret' filter is not allowed. No filters are allowed.`               |
| `filter=status`             | `filter`          | `The filter parameter must name the filters, such as filter[name][eq]=value.` |

The errors of operations, flags and values are listed in [Filter operations](01-filter-operations.md#errors).

## Related

- [Filter operations](01-filter-operations.md)
- [Date filters](02-date-filters.md)
- [Custom filters](03-custom-filters.md)
- [Sorting](04-sorting.md)
- [CRUD repository](../repositories/01-crud-repository.md)
- [Paginating lists](../pagination/00-paginating-lists.md)
