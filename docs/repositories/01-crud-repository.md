# CRUD repository

`Core\Repositories\CrudRepository` is the repository of a CRUD controller: it builds the query of a request (index
scope, filters, sorts, includes) and creates, updates, deletes and restores models. Write one per model that a
`CrudController` serves, and put the model's write logic in it.

## Setup

```php
namespace App\Repositories;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Core\Models\BaseModel;
use Core\Repositories\CrudRepository;

class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected string $resourceClass = InvoiceResource::class;

    protected array $allowedFilters = ['client_id', 'created_by_user_id'];

    protected array $defaultSorts = ['created_at' => 'desc'];
}
```

The controller receives it in its constructor (see [The CRUD controller](../http/controllers/01-crud-controller.md)):

```php
public function __construct(InvoicePolicy $policy, InvoiceRepository $repository)
{
    parent::__construct($policy, $repository);
}
```

The filter and sort properties come from `AllowsFilters` and `AllowsSorts` (see [Filters](../querying/00-filters.md)
and [Sorting](../querying/04-sorting.md)); `$resourceClass` comes from `AllowsEagerLoads` (see
[Eager loads](02-eager-loads.md)).

## The query of a request

`builder(?Request $request = null): BaseQuery` starts from the model's query and applies, in this order:

1. `index($request->user(), $request->all())` of the model's query (see [Index scopes](../queries/01-index-scopes.md));
2. the requested sorts, or the default ones, each qualified with its table;
3. the requested filters, skipping an empty value (`filter[name][eq]=`) except for the `null` operation; a value that a
   filter refuses answers 422 on `filter.{field}`;
4. the requested includes, validated against `$resourceClass` and eager loaded within the related queries' index
   scopes.

Without a request it uses the current one (`request()`).

## Finding

`find($id)` and `findOrFail($id)` look the model up among the ones, which the current request's user may list: they
apply the index scope (`index($request->user())`), but not the request's filters, sorts and includes, so a list's
query parameters don't hide a model from them. `findOrFail()` throws a `ModelNotFoundException`, a 404 in a request,
for a model the user can't list. In a console command or a queued job there is no user, and the index scope of most
queries then finds nothing: use the model's own query there (`Invoice::query()->find($id)`).

`create()` and `updateOrCreate()` use the model's plain query, whose global scopes (such as the organization's) still
apply. A scope wouldn't keep a user from writing: it would only make `updateOrCreate()` miss a model, which the user
can't list, and create a second one. Authorize writes with the policy before calling them, as `CrudController` does.

## Writing

| Method | Description |
|--------|-------------|
| `create(array $attributes, array $parents = []): BaseModel` | Makes the model of the attributes (mass assignment, so `$fillable` applies), associates the parents and saves it. |
| `update(BaseModel $model, array $attributes): bool` | `$model->update($attributes)`. |
| `updateOrCreate(array $attributes, array $values = []): BaseModel` | Updates the model, which has all the attributes, with the values, or creates one with both. |
| `delete(BaseModel $model): bool` | `$model->delete()`: a soft delete for a model with `SoftDeletes`. |
| `restore(BaseModel $model): bool` | Restores a soft-deleted model; `false` for a model without `restore()`. |
| `forceDelete(BaseModel $model): bool` | Deletes the model with `delete()`, then removes it permanently. |

`$parents` maps the names of the model's `BelongsTo` relations to the parent models. The controller of a nested route
passes the parents it resolved from the URL (see [Nested routes](../http/controllers/02-nested-routes.md)):

```php
$invoice = $repository->create(['number' => 'INV-1', 'total' => 100], ['client' => $client]);
```

`forceDelete()` calls `delete()` first, so whatever deleting does (the repository's own `delete()`, the model's
`deleting` listeners, such as a [locked record](../models/02-locked-records.md) refusing it) applies to permanent
deletes too. When `delete()` returns `false`, the model is kept and `forceDelete()` returns `false`. A model without
soft deletes is already gone after `delete()`.

The CRUD controller answers 500 when `update()`, `delete()`, `forceDelete()` or `restore()` returns `false`. Exceptions
that the model throws, such as `RecordLockedException` (409), pass through as they are.

## Adding logic

Override a method to add what the model needs, and call the parent:

```php
use Core\Helpers\CurrentOrganization;
use Core\Models\Sequence;
use Illuminate\Support\Facades\DB;

public function create(array $attributes, array $parents = []): BaseModel
{
    return DB::transaction(function () use ($attributes, $parents) {
        $number = Sequence::next(CurrentOrganization::get(), 'invoices:' . now()->year);

        return parent::create([...$attributes, 'number' => "INV-$number"], $parents);
    }, 3);
}
```

Keep the logic that every write needs in the repository, so the CRUD controller, other controllers and jobs share it.

## Related

- [Base repository](00-base-repository.md)
- [Eager loads](02-eager-loads.md)
- [Index scopes](../queries/01-index-scopes.md)
- [The CRUD controller](../http/controllers/01-crud-controller.md)
- [Nested routes](../http/controllers/02-nested-routes.md)
- [Restoring](../http/controllers/03-restoring.md)
- [Filters](../querying/00-filters.md)
- [Sorting](../querying/04-sorting.md)
- [Sequences](../models/04-sequences.md)
