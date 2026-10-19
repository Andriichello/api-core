# Paginating lists

Every list that a controller returns with `asPaginatedResponse()`, the `index` operation of a `CrudController`
included, is paginated by the `page[number]` and `page[size]` query parameters. A client asks for a page; the size
has a default and a maximum, which `config/pagination.php` sets.

## Usage

```http
GET /api/invoices?page[number]=2&page[size]=25
```

A client that builds the URL itself encodes the brackets, as in `page%5Bnumber%5D=2`. The parameters combine with the
filters, the sorts and the includes of the same request:

```http
GET /api/invoices?filter[status][eq]=draft&sort=-created_at&include=client&page[size]=50
```

The answer holds the items of the page under `data` and the counts and links under `meta` (see
[Paginated responses](01-paginated-responses.md)).

## How the parameters are read

| Request                                           | Page size | Page number |
|---------------------------------------------------|-----------|-------------|
| No parameters                                     | 100       | 1           |
| `page[size]=25&page[number]=3`                    | 25        | 3           |
| `page[size]=5000`                                 | 1000      | 1           |
| `page[size]=0`, `page[size]=-5` or `page[size]=abc` | 100     | 1           |
| `page[number]=0`, `page[number]=-1` or `page[number]=abc` | 100 | 1          |

A size above the maximum is capped at it rather than refused, and a size or a number that isn't a positive number
falls back to the default, so these parameters never answer `422`. A number past the last page answers `200` with
an empty `data`.

## Configuration

`config/pagination.php` holds the defaults. api-core merges it into the application's config, so an application
changes single keys in its own `config/pagination.php`, which
`php artisan vendor:publish --tag=api-core-config` publishes (with `config/api-core.php`). The keys have no
environment variables.

| Key                     | Env | Default | Description                                                                       |
|-------------------------|-----|---------|-----------------------------------------------------------------------------------|
| `max_results`           | —   | `1000`  | The largest page size; a larger `page[size]` is capped at it.                      |
| `default_size`          | —   | `100`   | The page size when `page[size]` is missing or not a positive number.               |
| `pagination_parameter`  | —   | `page`  | The name of the parameter, `page` in `page[number]`.                               |
| `number_parameter`      | —   | `number`| The key of the page number, `number` in `page[number]`.                            |
| `size_parameter`        | —   | `size`  | The key of the page size, `size` in `page[size]`.                                  |
| `use_simple_pagination` | —   | `false` | Pages without counting the total; see [Paginated responses](01-paginated-responses.md#simple-pagination). |
| `base_url`              | —   | `null`  | The URL of `meta.path` and of the links, instead of the request's URL.             |

With `'pagination_parameter' => 'p'`, for example, the parameters are `p[number]` and `p[size]`. Set `base_url`
when the API is reached through another URL than the one the application sees, such as behind a proxy.

## Stable pages

Without a `sort` parameter, a `CrudRepository` sorts by `id` (or by its own default sorts), so the pages don't
overlap. A requested sort replaces that order, so a sort by a column with repeated values, such as `status`, should
end with a unique one, as in `sort=status,id` (see [Sorting](../querying/04-sorting.md)).

## Related

- [Paginated responses](01-paginated-responses.md)
- [Paginating in code](02-paginating-in-code.md)
- [Filters](../querying/00-filters.md)
- [Sorting](../querying/04-sorting.md)
- [Configuration](../getting-started/03-configuration.md)
