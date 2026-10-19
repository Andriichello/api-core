# Nesting under a parent

`Core\Http\Controllers\Traits\NestsUnderParent` is for CRUD controllers of what belongs to a model and is authorized as
that model, such as its files or its settings, under routes like `invoices/{invoice}/files`. A user who can view the
invoice can read what belongs to it, and a user who can update the invoice can change it. `FileController` and
`SettingController` are built on it.

## How it differs from `$parents`

With [`$parents`](02-nested-routes.md), the controller's own policy authorizes the operations, with the permissions on
the controller's model. With `NestsUnderParent`, the controller takes the **parent model's policy**, and each request is
authorized as an ability on the parent model, which the route always names.

## Members

| Member | Description |
|---|---|
| `abstract protected function parentClass(): string` | The class of the parent model |
| `abstract protected function parentParameter(): string` | The route parameter of the parent model, e.g. `invoice` |
| `protected function parentAbilities(): array` | The ability on the parent, which each request's ability needs |
| `protected static function registerRoutes(string $uri, array $routes)` | Registers the routes of the listed operations |
| `protected function parentOf(CrudRequest $request): BaseModel` | The resolved parent of the request |

The default `parentAbilities()`:

| Request's ability | Ability on the parent |
|---|---|
| `viewAny`, `view` | `view` |
| `create`, `update`, `delete`, `forceDelete` | `update` |

A request whose ability isn't in the map, such as `restore`, is denied (403).

## Behavior

- **The parent is resolved first**, by its route parameter, within the user's index scope of the parent model. A
  parent the user can't see answers 404. A route without the parameter is a mistake: a `LogicException` (500).
- **Outer parents.** The controller's `$parents` name the parent model's own `BelongsTo` relations, for routes nested
  deeper, such as `clients/{client}/invoices/{invoice}/files` with `$parents = ['client']`. The parent is then looked
  up within them, so an invoice of another client answers 404.
- **Authorization.** `checkPolicy()` first makes sure the policy is the parent model's one (its `$model` is
  `parentClass()` or a subclass of it), or throws a `LogicException`, so a plain `CrudPolicy`, which has no model and
  would check no permissions, can't slip in. Then it maps the request's ability through `parentAbilities()`, and asks
  the policy's `before()` (the permissions, super admins) and then its method of that ability with the parent model,
  e.g. `InvoicePolicy::update($user, $invoice)`. There is no check of the target itself: show, update and destroy
  find it within the parent.
- **Queries and creation.** The parents, including the one named by `parentParameter()`, are in
  `$request->getParents()`, so `CrudController`'s `builder()` narrows the query with `whereBelongsTo()` for each of
  them, and `handleStore()` associates each of them. That works when the handled model has a `BelongsTo` relation
  named after each one; otherwise override `builder()` and `handleStore()` and use `parentOf()`, as `FileController`
  does for the morph relation of files.

## Writing a controller

Notes of an invoice, which have an `invoice()` relation, authorized by the invoice's policy:

```php
use App\Http\Requests\Invoices\StoreInvoiceNoteRequest;
use App\Http\Resources\InvoiceNoteCollection;
use App\Http\Resources\InvoiceNoteResource;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Repositories\InvoiceNoteRepository;
use Core\Http\Controllers\CrudController;
use Core\Http\Controllers\Traits\NestsUnderParent;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;

class InvoiceNoteController extends CrudController
{
    use NestsUnderParent;

    protected string $resourceClass = InvoiceNoteResource::class;

    protected string $collectionClass = InvoiceNoteCollection::class;

    protected array $operations = [
        'index' => IndexRequest::class,
        'store' => StoreInvoiceNoteRequest::class,
        'destroy' => DestroyRequest::class,
    ];

    public function __construct(InvoicePolicy $policy, InvoiceNoteRepository $repository)
    {
        parent::__construct($policy, $repository);
    }

    public static function routes(string $uri): void
    {
        static::registerRoutes($uri, [
            'index' => ['get', ''],
            'store' => ['post', ''],
            'destroy' => ['delete', '/{id}'],
        ]);
    }

    protected function parentClass(): string
    {
        return Invoice::class;
    }

    protected function parentParameter(): string
    {
        return 'invoice';
    }
}
```

The parameter and the abilities are methods, so the class, which uses the trait, overrides them, as a subclass does.
`FileController` and `SettingController` answer `parentParameter()` with their `$parameter` property, which their
subclasses set.

`registerRoutes()` registers a route for each operation, which is keyed by the operation and gives the HTTP method and
the URI's suffix, when the class's `$operations` lists it. It reads the property's declared value, so an operation
added in the constructor gets no route. Register the routes in a group with the middleware:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    InvoiceNoteController::routes('invoices/{invoice}/notes');
});
```

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/invoices/{invoice}/notes` | The invoice's notes, for a user who can view the invoice |
| `POST` | `/api/invoices/{invoice}/notes` | Add a note, for a user who can update the invoice |
| `DELETE` | `/api/invoices/{invoice}/notes/{id}` | Delete a note, for a user who can update the invoice |

## Related

- [Nested routes](02-nested-routes.md)
- [CRUD controller](01-crud-controller.md)
- [Custom operations](04-custom-operations.md)
- [File routes](../../files/02-file-routes.md)
- [Setting routes](../../settings/02-setting-routes.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [Index scopes](../../queries/01-index-scopes.md)
