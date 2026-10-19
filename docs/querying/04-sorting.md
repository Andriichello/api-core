# Sorting

The `sort` query parameter orders the lists of a `CrudController` (and any query that a `CrudRepository` builds). The
repository checks every sort against the ones it allows, and answers `422` for any other.

## Syntax

`sort` is a comma-separated list of fields. A field is sorted in ascending order, or in descending order with a
leading minus, and the fields apply in the order they are given:

| Query                      | SQL                                                                         |
|----------------------------|-----------------------------------------------------------------------------|
| `sort=number`              | `order by "invoices"."number" asc`                                          |
| `sort=-number`             | `order by "invoices"."number" desc`                                         |
| `sort=-created_at,number,id` | `order by "invoices"."created_at" desc, "invoices"."number" asc, "invoices"."id" asc` |
| No `sort`                  | `order by "invoices"."id" asc` (the default)                                |

```http
GET /api/invoices?sort=status,-created_at&page[size]=25
```

## Default sorts

Without a `sort` parameter, the repository applies its `$defaultSorts`:

```php
protected array $defaultSorts = ['created_at' => 'desc'];
```

Without them, it sorts by `id` ascending when `id` may be sorted by, else by `created_at` ascending when that may be,
and else not at all. A requested sort replaces the default one entirely, so a sort by a column with repeated values
should end with a unique one (`sort=status,id`) to keep the pages from overlapping (see
[Paginating lists](../pagination/00-paginating-lists.md)).

## Allowed sorts

A repository allows sorts by two properties of `AllowsSorts`, which `CrudRepository` uses:

```php
class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected bool $allowAllSorts = true;

    protected array $allowedSorts = [
        'issued' => ['created_at'],
        'client_name' => ['name', 'clients'],
    ];
}
```

With `$allowAllSorts` on (the default), the model's queryable columns may be sorted by, as well as the listed sorts:
the primary key, the fillable columns and the timestamps, without the hidden ones (see
[Filters](00-filters.md#allowed-filters) for why hidden columns are left out). With it off, only the listed sorts are
allowed, and an empty list allows none.

| Entry                                  | Sort          | Orders by                                   |
|----------------------------------------|---------------|---------------------------------------------|
| `'number'`                             | `number`      | `"invoices"."number"`                       |
| `'issued' => ['created_at']`           | `issued`      | `"invoices"."created_at"`                   |
| `'client_name' => ['name', 'clients']` | `client_name` | `"clients"."name"`, which needs the join    |

A hidden column is sorted by only when it is listed, by its name or as the column of an entry.

## Errors

Each error answers `422` with a `sort` error:

```json
{
    "message": "Sorting by the 'password' is not allowed. Allowed sorts: issued, client_name, id, number, status, client_id, created_at, updated_at.",
    "errors": {
        "sort": [
            "Sorting by the 'password' is not allowed. Allowed sorts: issued, client_name, id, number, status, client_id, created_at, updated_at."
        ]
    }
}
```

| Request                  | Message                                                              |
|--------------------------|----------------------------------------------------------------------|
| `sort=secret`            | `Sorting by the 'secret' is not allowed. Allowed sorts: …` (the list) |
| Same, with none allowed  | `Sorting by the 'secret' is not allowed. No sorts are allowed.`       |
| `sort[]=number`          | `The sort parameter must be a comma-separated list.`                 |

## In code

`getRequestedSorts($request)` returns the requested sorts, checked, as `['created_at' => 'desc', 'number' => 'asc']`;
`getDefaultSorts()` the default ones; `listOfAllowedSorts()` the names that may be sorted by.

## Related

- [Filters](00-filters.md)
- [Paginating lists](../pagination/00-paginating-lists.md)
- [CRUD repository](../repositories/01-crud-repository.md)
- [Base model](../models/00-base-model.md)
