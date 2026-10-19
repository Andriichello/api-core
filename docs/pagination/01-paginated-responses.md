# Paginated responses

`Core\Http\Resources\ResourcePaginator` gives a page of a list its JSON: the items in the controller's collection
under `data`, and the counts and the links of the pages under `meta`. `asPaginatedResponse()` builds it, so every
`index` of a `CrudController` answers in this shape.

## The JSON of a page

```http
GET /api/invoices?sort=-id&page[number]=2&page[size]=2
```

```json
{
    "data": [
        {"id": 3, "number": "INV-0003", "status": "paid"},
        {"id": 2, "number": "INV-0002", "status": "draft"}
    ],
    "meta": {
        "from": 3,
        "to": 4,
        "path": "https://example.com/api/invoices",
        "per_page": 2,
        "current_page": 2,
        "last_page": 3,
        "total": 5,
        "links": {
            "first": "https://example.com/api/invoices?sort=-id&page%5Bnumber%5D=1&page%5Bsize%5D=2",
            "prev": "https://example.com/api/invoices?sort=-id&page%5Bnumber%5D=1&page%5Bsize%5D=2",
            "self": "https://example.com/api/invoices?sort=-id&page%5Bnumber%5D=2&page%5Bsize%5D=2",
            "next": "https://example.com/api/invoices?sort=-id&page%5Bnumber%5D=3&page%5Bsize%5D=2",
            "last": "https://example.com/api/invoices?sort=-id&page%5Bnumber%5D=3&page%5Bsize%5D=2"
        }
    },
    "message": "OK"
}
```

(The items show a few of their attributes; each is output by the collection's resource.)

| Field          | Description                                                                                 |
|----------------|---------------------------------------------------------------------------------------------|
| `from`, `to`   | The positions of the first and the last item of the page in the whole list; `null` when the page is empty. |
| `path`         | The URL of the list without its query, or `pagination.base_url` when it is set.             |
| `per_page`     | The page size that was used, after the default and the maximum.                             |
| `current_page` | The page number that was used.                                                              |
| `last_page`    | The number of the last page; `1` for an empty list.                                         |
| `total`        | The number of items in the whole list.                                                      |
| `links`        | The URLs of the first, previous, current, next and last pages; `prev` is `null` on the first page and `next` on the last. |

The links keep every parameter of the request, such as `filter`, `sort` and `include`, and set `page[number]` and
`page[size]`, with the brackets URL-encoded. A client can follow `links.next` until it is `null`.

A page number past the last page answers with an empty `data`, `from` and `to` as `null`, and `next` as `null`, while
`total` and `last_page` still describe the list.

## Simple pagination

With `pagination.use_simple_pagination` set to `true`, a page is read without counting the whole list, which saves a
`count` query on large tables. The query fetches one item more than the page size, to know whether a next page
exists. `total`, `last_page` and `links.last` are then `null`, and `links.next` is `null` on the last page.

## Using the paginator

`asPaginatedResponse()` covers the usual case (see [Paginating in code](02-paginating-in-code.md)). A paginator can
also be made from any Laravel paginator and a collection class:

```php
use Core\Http\Resources\ResourcePaginator;
use Core\Http\Responses\ApiResponse;

$paginator = new ResourcePaginator($this->paginate($query, $request), InvoiceCollection::class);

return ApiResponse::ok($paginator->toArray());
```

| Method                     | Returns                                                                       |
|----------------------------|-------------------------------------------------------------------------------|
| `toArray()`                | `['data' => collection, 'meta' => meta]`.                                     |
| `meta()`                   | The `meta` array.                                                             |
| `getResourceCollection()`  | The items of the page in the collection class.                                |
| `total()`, `lastPage()`    | The counts, or `null` for a simple paginator.                                 |
| `paginator()`              | The paginator it was made from.                                               |

A collection class that isn't a `ResourceCollection` throws an `Exception` with
`Invalid collectionClass. It must be an instance of ResourceCollection.`

## Related

- [Paginating lists](00-paginating-lists.md)
- [Paginating in code](02-paginating-in-code.md)
- [Collections](../http/resources/01-collections.md)
- [Responses](../http/01-responses.md)
