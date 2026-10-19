# Date filters

`Core\Http\Filters\DateFilter` filters timestamps and dates as dates rather than as strings: it checks that a value is
a real date, reads it in the user's timezone, and treats a date without time on a timestamp as the whole day. A
repository picks it by itself for the model's timestamps and its date columns.

## When it applies

A filter whose class is chosen by its column (see [Filters](00-filters.md#allowed-filters)) is a date filter, when
the column is one of the model's timestamps (`created_at`, `updated_at`) or is cast to `date`, `datetime`,
`timestamp` or their `immutable_` forms. A filter can also be allowed with it, or without it, explicitly:

```php
protected array $allowedFilters = [
    'due_on' => DateFilter::class,
    // Compares the strings, as they are given
    'created' => [DefaultFilter::class, 'created_at'],
];
```

## Values

A value is a date, optionally with a time and an offset:

| Value                         | Means                                                      |
|-------------------------------|------------------------------------------------------------|
| `2026-06-30`                  | The whole day, in the user's timezone.                     |
| `2026-06-30 12:30`            | 12:30:00 that day, in the user's timezone.                 |
| `2026-06-30T12:30:45`         | That second, in the user's timezone.                       |
| `2026-06-30T12:00:00Z`        | That second in UTC.                                        |
| `2026-06-30T12:00:00+02:00`   | That second at the offset (also written `+0200`).          |

Anything else answers `422`: words (`yesterday`), impossible dates (`2026-02-30`), times past `23:59:59`, offsets
beyond 14 hours and other formats (`30/06/2026`). In a URL, `+` is written `%2B`.

## The whole day

On a timestamp, a date without time stands for the whole day, from its start up to the start of the next day:

| Query (UTC user)                         | SQL                                                                                        |
|------------------------------------------|--------------------------------------------------------------------------------------------|
| `filter[created_at][eq]=2026-06-30`      | `("invoices"."created_at" >= '2026-06-30 00:00:00' and "invoices"."created_at" < '2026-07-01 00:00:00')` |
| `filter[created_at][not][eq]=2026-06-30` | `("invoices"."created_at" < '2026-06-30 00:00:00' or "invoices"."created_at" >= '2026-07-01 00:00:00')`  |
| `filter[created_at][gt]=2026-06-30`      | `"invoices"."created_at" >= '2026-07-01 00:00:00'`                                         |
| `filter[created_at][gte]=2026-06-30`     | `"invoices"."created_at" >= '2026-06-30 00:00:00'`                                         |
| `filter[created_at][lt]=2026-06-30`      | `"invoices"."created_at" < '2026-06-30 00:00:00'`                                          |
| `filter[created_at][lte]=2026-06-30`     | `"invoices"."created_at" < '2026-07-01 00:00:00'`                                          |

So `gt` starts after the day and `lte` includes all of it; `gte` with June 1 and `lte` with June 30 give the whole of
June. `not` gives the opposite comparison (`not gt` is `< '2026-07-01 00:00:00'`).

A date and time is compared exactly: `filter[created_at][lte]=2026-06-30T12:30:45` is
`"invoices"."created_at" <= '2026-06-30 12:30:45'`.

`in` takes a comma-separated value or a list, of whole days and moments mixed, and matches any of them; with `not`,
none of them:

```http
GET /api/invoices?filter[created_at][in]=2026-06-30,2026-07-02T08:00
```

## Timezones

Values without an offset are read in the timezone of the user who sends the request (the `timezone` column of
`User`), or in UTC when the user has none or it isn't a valid timezone. They are converted to the application's
timezone (`app.timezone`), in which the timestamps are stored. For a user in `Europe/Kyiv` (UTC+3 in summer) and an
application in UTC:

| Query                                         | Condition on `"invoices"."created_at"`                                           |
|-----------------------------------------------|----------------------------------------------------------------------------------|
| `filter[created_at][eq]=2026-06-30`           | `>= '2026-06-29 21:00:00' and < '2026-06-30 21:00:00'`                           |
| `filter[created_at][lte]=2026-06-30 12:00`    | `<= '2026-06-30 09:00:00'`                                                       |
| `filter[created_at][lte]=2026-06-30T12:00:00Z`| `<= '2026-06-30 12:00:00'`                                                       |

A value with an offset keeps it, whoever sends it.

## Date columns

A column cast to `date` (or `immutable_date`) holds days without time, so its values are dates without time, compared
as they are given, with no whole-day range and no timezone: `filter[due_on][lte]=2026-06-30` is
`"invoices"."due_on" <= '2026-06-30'`.

## Errors

| Request                                  | Message                                                                                  |
|------------------------------------------|------------------------------------------------------------------------------------------|
| `filter[created_at][lte]=yesterday`      | `A date filter takes a date, or a date and time, such as 2026-06-30 or 2026-06-30T12:00:00Z.` |
| `filter[due_on][eq]=2026-06-30 12:00`    | `A date filter takes a date without time, such as 2026-06-30.`                           |
| `filter[created_at][like]=2026%25`       | `The 'like' operation is not supported by date filters.`                                 |
| `filter[created_at][ci][eq]=2026-06-30`  | `The 'ci' flag is not supported by date filters.`                                        |

`contains` is refused like `like`. The `null` operation works as on any column: `filter[paid_at][null]=false` is
`"invoices"."paid_at" is not null`. The errors answer `422`, keyed by `filter.{name}`.

## Related

- [Filters](00-filters.md)
- [Filter operations](01-filter-operations.md)
- [Custom filters](03-custom-filters.md)
- [The User model](../users/00-user-model.md)
