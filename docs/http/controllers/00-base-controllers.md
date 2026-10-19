# Base controllers

`BaseController` and `ResourceController` are the abstract bases of api-core's controllers. Extend `ResourceController`
for endpoints that answer with models but aren't CRUD operations; `CrudController` extends it for the ones that are.

## BaseController

`Core\Http\Controllers\BaseController` uses Laravel's `AuthorizesRequests` (`$this->authorize()`) and
`ValidatesRequests` (`$this->validate()`) traits, and api-core's `WithPagination` trait, which implements
`Core\Http\Controllers\Interfaces\WithPaginationInterface`. It doesn't extend Laravel's `Illuminate\Routing\Controller`.

`WithPagination` reads the page from the query (`page[number]` and `page[size]`, named in `config/pagination.php`):

| Method | Returns |
|---|---|
| `paginate($builder, ?Request $request = null)` | The paginator of the requested page of the query |
| `paginateQuery($builder, ?Request $request = null)` | The query limited to the requested page (`forPage()`) |
| `getPageNumber(?Request $request = null)` | `page[number]`, or 1 when it's missing or not a positive number |
| `getPageSize(?Request $request = null)` | `page[size]` capped at the maximum, or the default when it's missing or not positive |
| `getMaxPageSize()` | `pagination.max_results` (1000) |
| `getDefaultPageSize()` | `pagination.default_size` (100) |
| `getPaginationMethod()` | `simplePaginate` when `pagination.use_simple_pagination` is on, else `paginate` |

The request defaults to the current one. See [Paginating in code](../../pagination/02-paginating-in-code.md).

## ResourceController

`Core\Http\Controllers\ResourceController` names the resource and the collection, which wrap the controller's models:

```php
protected string $resourceClass = InvoiceResource::class;     // a BaseResource
protected string $collectionClass = InvoiceCollection::class; // a BaseCollection
```

`getResourceClass()` and `getCollectionClass()` return them. Its methods wrap models in them, and in responses:

| Method | Returns |
|---|---|
| `asResource($model, ?array $includes = null, ?array $appends = null)` | The model (or an array) in the resource |
| `asCollection(Collection $models, ?array $includes = null, ?array $appends = null)` | The models in the collection |
| `asResourceResponse($model, ?array $includes = null, ?array $appends = null)` | `ApiResponse::ok()` with the resource in `data` |
| `asCollectionResponse(Collection $models, ?array $includes = null, ?array $appends = null)` | `ApiResponse::ok()` with all the models in `data`, without `meta` |
| `asPaginatedResponse($builder, ?Request $request = null)` | `ApiResponse::ok()` with the requested page in `data` and the pagination in `meta` |

`asPaginatedResponse()` runs the given query for the requested page, so pass it a query of models.

Without `$includes` and `$appends`, a resource takes the request's `include` and `append` query parameters. When given,
they're used instead, still limited to the resource's allowed ones. An include is output only when its relation is
loaded, so load it first:

```php
return $this->asResourceResponse($invoice->load('client'), ['client']);
```

## A controller that isn't CRUD

A page of a client's overdue invoices, at `GET /api/clients/{client}/overdue-invoices?page[number]=2`:

```php
namespace App\Http\Controllers;

use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use Core\Http\Controllers\ResourceController;
use Core\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class OverdueInvoiceController extends ResourceController
{
    protected string $resourceClass = InvoiceResource::class;

    protected string $collectionClass = InvoiceCollection::class;

    public function index(Request $request, string $client): ApiResponse
    {
        $owner = Client::query()->index($request->user())->findOrFail($client);

        $query = Invoice::query()
            ->index($request->user())
            ->whereBelongsTo($owner)
            ->where('due_at', '<', now())
            ->whereNull('paid_at');

        return $this->asPaginatedResponse($query, $request);
    }
}
```

```php
Route::get('clients/{client}/overdue-invoices', [OverdueInvoiceController::class, 'index']);
```

Unlike a `CrudController`, such a controller authorizes and validates on its own, with `$this->authorize()`, a form
request or a policy. `NotificationController` is one of api-core's controllers built this way.

## Related

- [CRUD controller](01-crud-controller.md)
- [Responses](../01-responses.md)
- [Base resource](../resources/00-base-resource.md)
- [Collections](../resources/01-collections.md)
- [Includes](../resources/02-includes.md)
- [Appends](../resources/03-appends.md)
- [Paginating lists](../../pagination/00-paginating-lists.md)
- [Paginating in code](../../pagination/02-paginating-in-code.md)
- [Index scopes](../../queries/01-index-scopes.md)
