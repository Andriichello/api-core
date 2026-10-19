# Filter operations

A filter applies one operation (`Core\Enums\FilterOperation`) to its value, changed by any of two flags
(`Core\Enums\FilterFlag`): `not`, which negates it, and `ci`, which ignores the case. This document lists what each
one does, with the SQL that `DefaultFilter` builds for it.

## Syntax

Flags come first, in any order, and the operation last: `filter[name][not][ci][eq]=x` and
`filter[name][ci][not][eq]=x` are the same. A filter without an operation is `eq`, so `filter[name][not]=x` is
`filter[name][not][eq]=x`.

## Operations

| Operation  | Query                                   | SQL                                                          |
|------------|-----------------------------------------|--------------------------------------------------------------|
| `eq`       | `filter[status][eq]=draft`              | `"invoices"."status" = 'draft'`                              |
| `in`       | `filter[status][in]=draft,sent`         | `"invoices"."status" in ('draft', 'sent')`                   |
| `null`     | `filter[paid_at][null]=true`            | `"invoices"."paid_at" is null`                               |
| `like`     | `filter[number][like]=INV-2026%25`      | `"invoices"."number" LIKE 'INV-2026%'`                       |
| `contains` | `filter[number][contains]=50%25_off`    | `"invoices"."number" LIKE '%50!%!_off%' ESCAPE '!'`          |
| `gt`       | `filter[total][gt]=100`                 | `"invoices"."total" > '100'`                                 |
| `gte`      | `filter[total][gte]=100`                | `"invoices"."total" >= '100'`                                |
| `lt`       | `filter[total][lt]=100`                 | `"invoices"."total" < '100'`                                 |
| `lte`      | `filter[total][lte]=100`                | `"invoices"."total" <= '100'`                                |

- `in` takes a comma-separated value or a list (`filter[status][in][]=draft&filter[status][in][]=sent`).
- `null` takes true or false. An empty value, `1`, `true`, `yes` and `on` ask for null; `0`, `false`, `no` and `off`
  ask for not null, so `filter[paid_at][null]=false` is `"invoices"."paid_at" is not null`.
- `like` takes the pattern as it is, so the client writes the wildcards `%` and `_`.
- `contains` matches the value anywhere in the column, literally: its `%`, `_` and `!` are escaped with `!` (rather
  than a backslash, which MySQL would need written differently), so `50%` finds "50% off" but not "500 off".
- `like` and `contains` take a list of values too, and then match any of them:
  `filter[number][like][]=INV%25&filter[number][like][]=CR%25` is
  `("invoices"."number" LIKE 'INV%' or "invoices"."number" LIKE 'CR%')`.

A filter on a timestamp or a date column is a [date filter](02-date-filters.md), which reads dates rather than
comparing strings.

## Flags

`not` negates the operation:

| Query                                | SQL                                                             |
|--------------------------------------|-----------------------------------------------------------------|
| `filter[status][not][eq]=draft`      | `"invoices"."status" != 'draft'`                                |
| `filter[status][not][in]=paid,void`  | `"invoices"."status" not in ('paid', 'void')`                   |
| `filter[paid_at][not][null]=true`    | `"invoices"."paid_at" is not null`                              |
| `filter[number][not][like]=CR%25`    | `"invoices"."number" NOT LIKE 'CR%'`                            |
| `filter[total][not][gt]=100`         | `"invoices"."total" <= '100'`                                   |
| `filter[total][not][gte]=100`        | `"invoices"."total" < '100'`                                    |

With a list, `not like` and `not contains` match none of the values (`and` instead of `or`).

`ci` lowercases both sides of the comparison, so the case doesn't matter:

| Query                                       | SQL                                                                |
|---------------------------------------------|--------------------------------------------------------------------|
| `filter[status][ci][eq]=DRAFT`              | `LOWER("invoices"."status") = LOWER('DRAFT')`                      |
| `filter[status][ci][in]=Draft,Sent`         | `LOWER("invoices"."status") IN (LOWER('Draft'), LOWER('Sent'))`    |
| `filter[number][ci][contains]=inv`          | `LOWER("invoices"."number") LIKE LOWER('%inv%') ESCAPE '!'`        |
| `filter[number][ci][not][like]=%25-CR`      | `LOWER("invoices"."number") NOT LIKE LOWER('%-CR')`                |

The `null` operation and date filters don't take `ci`.

## Errors

The errors answer `422`, keyed by `filter.{name}`:

| Request                                  | Message                                                                                |
|------------------------------------------|----------------------------------------------------------------------------------------|
| `filter[status][between]=a`              | `Unknown filter operation or flag 'between'. Operations: eq, in, null, like, contains, gt, gte, lt, lte; flags: not, ci.` |
| `filter[status][eq][not]=draft`          | `The 'eq' operation must come after the flags, such as [not][eq].`                     |
| `filter[total][gt][]=1&filter[total][gt][]=2` | `A list of values is only supported by the 'in', 'like' and 'contains' operations.` |
| `filter[paid_at][null]=maybe`            | `The 'null' operation takes true or false, such as [null]=false.`                      |
| `filter[paid_at][ci][null]=1`            | `The 'ci' flag is not supported by the 'null' operation.`                              |
| `null` as the value, in a JSON body       | `A filter value must be a string or a list of strings.`                                |

## Related

- [Filters](00-filters.md)
- [Date filters](02-date-filters.md)
- [Custom filters](03-custom-filters.md)
- [Enums](../models/05-enums.md)
