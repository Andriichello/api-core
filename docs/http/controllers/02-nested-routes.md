# Nested routes

`CrudController::$parents` nests a controller's routes under the models its model belongs to, such as
`clients/{client}/invoices/{id}`. The operations then see only the parents' models, and a new model belongs to the
parents. Use it when a model's routes should be reached through its parents.

## Setup

Each name in `$parents` is both a route parameter and the name of a `BelongsTo` relation of the controller's model,
from the outermost parent to the innermost:

```php
class Invoice extends BaseModel
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

class InvoiceController extends CrudController
{
    protected array $parents = ['client', 'project'];

    // the resources, operations and constructor, as for any CRUD controller
}
```

The same controller serves routes nested under both parents, under one of them, or under none:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    foreach (['clients/{client}/invoices', 'clients/{client}/projects/{project}/invoices'] as $uri) {
        Route::get($uri, [InvoiceController::class, 'index']);
        Route::post($uri, [InvoiceController::class, 'store']);
        Route::get("$uri/{id}", [InvoiceController::class, 'show']);
        Route::patch("$uri/{id}", [InvoiceController::class, 'update']);
        Route::delete("$uri/{id}", [InvoiceController::class, 'destroy']);
    }
});
```

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/clients/{client}/invoices` | A page of the client's invoices |
| `POST` | `/api/clients/{client}/invoices` | Create an invoice of the client |
| `GET` | `/api/clients/{client}/projects/{project}/invoices/{id}` | Show an invoice of the client's project |

## Behavior

- **Only the parents in the route are resolved.** A parameter the route doesn't have is skipped, so
  `clients/{client}/invoices` resolves only the client, and `invoices/{id}` none.
- **A parent is found within what the user may list**, through its own query's `index($user)`, so a parent of another
  organization, or one the user can't see, answers 404. This holds when the route binds the parameter
  (`Route::bind()`, `Route::model()`) too: a bound model is looked up again by its key.
- **An inner parent must belong to the outer ones.** When the inner parent's model has a relation named after an outer
  parent, it's looked up within that parent: `clients/1/projects/7/invoices` answers 404 when project 7 isn't
  client 1's.
  An inner model without such a relation isn't narrowed by the outer parent.
- **Parents come first.** They're resolved before the policy and the validation, so a missing parent answers 404, even
  to a user who may not do the operation or who sent invalid data.
- **The operations are narrowed to the parents.** `builder()` adds `whereBelongsTo($parent, $relation)` for each
  parent, so the index lists only their models, and show, update, destroy and restore find only them (404 otherwise).
- **A new model belongs to the parents.** `handleStore()` passes them to `CrudRepository::create()`, which associates
  each one through its relation before saving. Their keys are set although they aren't fillable, and whatever the body
  says about them.

```http
POST /api/clients/2/invoices
Content-Type: application/json

{"number": "INV-0007", "client_id": 9}
```

```json
{
    "data": {
        "id": 7,
        "organization_id": 1,
        "client_id": 2,
        "project_id": null,
        "number": "INV-0007",
        "created_at": "2026-10-20T12:00:00.000000Z",
        "updated_at": "2026-10-20T12:00:00.000000Z"
    },
    "message": "Created"
}
```

## Using the parents

The request holds the resolved parents, keyed by their names:

```php
protected function handleStore(StoreRequest $request): ApiResponse
{
    $client = $request->getParent('client'); // or null on a route without {client}

    // ...
}
```

`getParents()` returns all of them. The parents are set after the request is made, and `rules()` runs when it's made,
so `getParents()` is still empty in `rules()`; a rule that depends on a parent reads the route parameter
(`$this->route('client')`) instead.

## Errors

| Status | When |
|---|---|
| 404 | A parent doesn't exist, the user can't see it, or it isn't under the outer parent: `Not found.` |
| 404 | The model isn't one of the parents' |

## Related

- [CRUD controller](01-crud-controller.md)
- [Nesting under a parent](05-nesting-under-a-parent.md)
- [CRUD requests](../requests/01-crud-requests.md)
- [CRUD repository](../../repositories/01-crud-repository.md)
- [Index scopes](../../queries/01-index-scopes.md)
- [Organization-scoped models](../../organizations/03-organization-scoped-models.md)
