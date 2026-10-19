# Paginating in code

The `WithPagination` trait reads the page parameters and paginates query builders, `asPaginatedResponse()` turns a
query into a paginated response, and `BaseQuery::chunkPaginated()` walks through a page, or any limited query, in
chunks. Use them for lists outside of a `CrudController`'s `index`, and for exports.

## WithPagination

`Core\Http\Controllers\Traits\WithPagination` (with `WithPaginationInterface`) is used by `BaseController`, so every
controller that extends it has these methods:

| Method                              | Description                                                                         |
|-------------------------------------|-------------------------------------------------------------------------------------|
| `paginate($builder, $request)`      | The requested page as a Laravel paginator (`paginate()` or `simplePaginate()`).     |
| `paginateQuery($builder, $request)` | The builder limited to the requested page (`forPage()`), without running it.        |
| `getPageSize($request)`             | The requested size, capped at the maximum; the default when it isn't positive.     |
| `getPageNumber($request)`           | The requested number; `1` when it isn't positive.                                   |
| `getDefaultPageSize()`              | `pagination.default_size`.                                                          |
| `getMaxPageSize()`                  | `pagination.max_results`.                                                           |
| `getPaginationMethod()`             | `simplePaginate` when `pagination.use_simple_pagination` is on, else `paginate`.    |

The `$request` arguments are optional and default to the current request. Both kinds of builder work: an Eloquent
one and a query builder.

A controller changes its own limits by overriding the getters, for example to keep the pages of a heavy list small:

```php
public function getMaxPageSize(): int
{
    return 50;
}
```

## Paginated responses of custom lists

`ResourceController::asPaginatedResponse($builder, $request)` paginates the builder and answers with the page in the
controller's `$collectionClass` (see [Paginated responses](01-paginated-responses.md)). `CrudController::handleIndex()`
and `NotificationController::index()` use it. A list built by hand scopes its query to the user itself, with
`index()`, as a `CrudRepository` does:

```php
class OverdueInvoiceController extends ResourceController
{
    protected string $resourceClass = InvoiceResource::class;

    protected string $collectionClass = InvoiceCollection::class;

    public function __invoke(Request $request): ApiResponse
    {
        $query = Invoice::query()
            ->index($request->user())
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->orderBy('id');

        return $this->asPaginatedResponse($query, $request);
    }
}
```

A query from a repository already has the filters, the sorts, the includes and the index scope of the request:
`$this->asPaginatedResponse($repository->builder($request), $request)`.

## chunkPaginated

`BaseQuery::chunkPaginated($callback, $count = 1000)` passes the results of a query to the callback in chunks of
`$count`. It respects the query's offset and limit: the chunks start at the offset and stop at the limit, or at the
end of the results when there is no limit. Empty chunks aren't passed, and the callback returning `false` stops it.
Each chunk is read with a clone of the query, so the query itself keeps its offset and limit.

With `paginateQuery()`, it exports exactly the page that the list would show, without loading it at once:

```php
$query = $this->paginateQuery($this->repository->builder($request), $request);

$query->chunkPaginated(function (Collection $invoices) use ($file) {
    foreach ($invoices as $invoice) {
        fputcsv($file, [$invoice->number, $invoice->total]);
    }
}, 200);
```

With `page[number]=2&page[size]=5`, the query has offset 5 and limit 5, so chunks of 2 give the 6th and 7th, the 8th
and 9th, and the 10th result. Each chunk is a query of its own, so give the query an order, as for any pagination.

## Related

- [Paginating lists](00-paginating-lists.md)
- [Paginated responses](01-paginated-responses.md)
- [Base controllers](../http/controllers/00-base-controllers.md)
- [Base query](../queries/00-base-query.md)
- [Index scopes](../queries/01-index-scopes.md)
