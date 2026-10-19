# CRUD controller

`Core\Http\Controllers\CrudController` lists, shows, creates, updates and deletes the models of a repository, with
authorization, validation, scoping and JSON responses wired in. An application extends it once per model, and
registers a route for each operation it offers.

## Writing a controller

```php
namespace App\Http\Controllers;

use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Policies\InvoicePolicy;
use App\Repositories\InvoiceRepository;
use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;

class InvoiceController extends CrudController
{
    protected string $resourceClass = InvoiceResource::class;

    protected string $collectionClass = InvoiceCollection::class;

    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'store' => StoreInvoiceRequest::class,
        'update' => UpdateInvoiceRequest::class,
        'destroy' => DestroyRequest::class,
    ];

    public function __construct(InvoicePolicy $policy, InvoiceRepository $repository)
    {
        parent::__construct($policy, $repository);
    }
}
```

- **The model** is the repository's (`CrudRepository::$model`); the controller never names it.
- **The repository** (a `CrudRepository`) builds the queries, with the user's index scope, filters, sorts and includes,
  and creates, updates, deletes and restores the models.
- **The policy** (a `CrudPolicy`) authorizes each operation.
- **The resources** (`$resourceClass` and `$collectionClass`) turn models into JSON.
- **`$operations`** maps the operations the controller handles to the classes of their requests. The default list has
  `index`, `show`, `store`, `update` and `destroy` with api-core's own requests. Those of `store` and `update` have no
  rules, so a controller lists its own `StoreRequest` and `UpdateRequest` subclasses. Leave an operation out to turn
  it off.
- **The constructor** names the concrete policy and repository, so the container can inject them.

## Routes

`CrudController` has no `routes()` of its own. An application registers a route per operation, naming its method, with
the model's id as `{id}`, which the requests read the target from:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
});
```

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/invoices` | A page of the invoices: `filter`, `sort`, `include`, `append` and `page` apply |
| `POST` | `/api/invoices` | Create an invoice: 201, `Created` |
| `GET` | `/api/invoices/{id}` | Show an invoice |
| `PATCH` | `/api/invoices/{id}` | Update the given fields of an invoice: `Updated` |
| `DELETE` | `/api/invoices/{id}` | Delete an invoice: `Deleted`; with `force=1`, delete it permanently |

The operations are real methods: `index()`, `show()`, `store()`, `update()`, `destroy()` and the opt-in `restore()`. So
attributes chained before a route apply to it, as they do to any controller's:

```php
Route::middleware('auth:sanctum')->prefix('v2')->get('invoices', [InvoiceController::class, 'index']);
```

A route to an operation that isn't in `$operations` throws a `BadMethodCallException`, which answers 500, so register
routes only for the operations the controller lists.

## How a call is handled

Every operation goes through `handle($operation, $request)`:

1. `prepareRequest()` checks that the operation is in `$operations`, and makes its request with `createFrom()`, which
   applies `defaults()` and `overrides()` and builds the validator (so `rules()` runs now).
2. The parents of a nested route are resolved (see [Nested routes](02-nested-routes.md)): 404 when one isn't found.
3. `checkPolicy()` asks the policy's `determine()`. It checks the user's permissions for the request's ability
   (`viewAny`, `view`, `create`, `update`, `delete`, `forceDelete` or `restore`): 403 when they're missing. For a
   request with a target, it then finds the target within the user's index scope (404 when it isn't found), and asks
   the policy's method of the ability about it (403 when it's denied).
4. `validateResolved()` validates the input: 422 when it's invalid.
5. For `store`, `update` and `restore`, `validateRequestedOfResource()` checks the requested includes and appends
   against the controller's resource: 422 when one isn't allowed, before anything changes.
6. `handle{Operation}()` (`handleIndex()`, `handleStore()`, ...) does the work and returns the response.

Authorization comes before validation, so a user who may not create invoices gets 403, not 422, for an invalid body.
A user who has the permission gets 404, never 403, for a model they can't see.

## The handlers

| Handler | What it does | Response |
|---|---|---|
| `handleIndex(IndexRequest)` | Pages the query of `builder()` | 200 `OK`, `data` and `meta` |
| `handleShow(ShowRequest)` | Finds the model in `builder()` | 200 `OK` |
| `handleStore(StoreRequest)` | `$repository->create($request->validated(), $request->getParents())` | 201 `Created` |
| `handleUpdate(UpdateRequest)` | Finds the model, `$repository->update($model, $request->validated())` | 200 `Updated`, the fresh model |
| `handleDestroy(DestroyRequest)` | Finds the model, `delete()`, or `forceDelete()` with `force` | 200 `Deleted`, no `data` |
| `handleRestore(RestoreRequest)` | See [Restoring](03-restoring.md) | 200 `Restored` |

Only validated fields are saved: input without a rule is ignored. When the repository fails to save or delete, the
handler throws, which answers 500.

`builder($request)` is the query every handler but `handleStore()` starts from: the repository's `builder()`, with the
user's index scope (`index($user)`), the requested sorts (or the default ones), filters and includes, narrowed to the
parents of a nested route. Override it to narrow every operation at once:

```php
use Core\Http\Requests\CrudRequest;
use Core\Queries\BaseQuery;

protected function builder(CrudRequest $request): BaseQuery
{
    return parent::builder($request)->whereNull('archived_at');
}
```

## Example

```http
PATCH /api/invoices/5
Authorization: Bearer {access_token}
Content-Type: application/json

{"number": "INV-0005"}
```

```json
{
    "data": {
        "id": 5,
        "organization_id": 1,
        "client_id": 2,
        "number": "INV-0005",
        "created_at": "2026-10-01T09:30:00.000000Z",
        "updated_at": "2026-10-20T12:00:00.000000Z"
    },
    "message": "Updated"
}
```

## Errors

| Status | When |
|---|---|
| 401 | The route's `auth:sanctum` finds no user |
| 403 | The policy denies the operation: `This action is unauthorized.` |
| 404 | The model or a parent doesn't exist, or the user can't see it: `Not found.` |
| 409 | A locked record or a status transition refuses the change |
| 422 | Invalid input, filters, sorts, includes or appends; an update without fields; a body that isn't an object |
| 500 | The operation isn't in `$operations`, or the repository failed |

## Related

- [Base controllers](00-base-controllers.md)
- [Nested routes](02-nested-routes.md)
- [Restoring](03-restoring.md)
- [Custom operations](04-custom-operations.md)
- [CRUD requests](../requests/01-crud-requests.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [CRUD repository](../../repositories/01-crud-repository.md)
- [Filters](../../querying/00-filters.md)
- [Sorting](../../querying/04-sorting.md)
- [Includes](../resources/02-includes.md)
- [Errors](../02-errors.md)
- [Building a feature](../../getting-started/06-building-a-feature.md)
