# Targets

A target is the model a request acts on, such as the invoice of `GET /api/invoices/5`. `WithTargetInterface` and the
`WithTarget` trait give a request its target's id and find the target within what the user may list, which is how a
CRUD policy gets the model it checks. Use them in any request that acts on one model.

## The interface

`Core\Http\Requests\Interfaces\WithTargetInterface`:

| Method | Returns |
|---|---|
| `getId(): mixed` | The id of the target |
| `setId(mixed $value): static` | The request, with the id set |
| `targetQuery(BaseModel\|string $model): BaseQuery` | The query that finds the target |
| `target(BaseModel\|string $model): ?BaseModel` | The target, or `null` when it isn't found |
| `targetOrFail(BaseModel\|string $model): BaseModel` | The target, or a `ModelNotFoundException` (404) |

`Core\Http\Requests\Traits\WithTarget` implements it. `ShowRequest`, `UpdateRequest`, `DestroyRequest` and
`RestoreRequest` use it.

## How the target is found

- **The id** is the route's `{id}` parameter, or else the input's `id`. `setId()` sets it instead.
- **The query** is `$model::query()->whereKey($id)->index($request->user(), $request->all())`: the target is looked up
  within the user's index scope, with the request's input as the parameters of `index()`, so a model the user can't
  see isn't found.
- **The model** is passed as a class or an instance: `$request->targetOrFail(Invoice::class)`.
- **The target is loaded once.** Asking again for the same class, or an instance of it, returns the loaded model
  without a query. `setId()` forgets it, so the next call loads the model of the new id.

Once the user's permissions allow the request's ability, `CrudPolicy::determine()` calls `targetOrFail()` with the
policy's model for every request with a target, so a model the user can't see answers 404 before the policy's method
of the ability runs.

## Narrowing the query

A request changes which models can be its target by overriding `targetQuery()`. `DestroyRequest` and `RestoreRequest`
keep the trait's query under another name and add to it:

```php
use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;

class SendInvoiceRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget {
        targetQuery as protected scopedTargetQuery;
    }

    protected ?string $ability = 'send';

    public function targetQuery(BaseModel|string $model): BaseQuery
    {
        return $this->scopedTargetQuery($model)->whereNull('sent_at');
    }
}
```

An invoice that was sent already is then not found: 404, before the policy's `send()` runs. `RestoreRequest` adds
`whereTrashed()` this way, and `DestroyRequest` drops the soft-deleting scope when `force` is asked for.

## Outside of CRUD controllers

A request that Laravel injects into a controller method can find its target the same way:

```php
use Core\Http\Requests\BaseRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;

class DuplicateInvoiceRequest extends BaseRequest implements WithTargetInterface
{
    use WithTarget;
}

public function duplicate(DuplicateInvoiceRequest $request): ApiResponse
{
    $invoice = $request->targetOrFail(Invoice::class);

    // ...
}
```

The CRUD controller's handlers don't use the loaded target: they find the model again through the controller's
`builder()`, which adds the filters, includes and parents of the request.

## Related

- [CRUD requests](01-crud-requests.md)
- [Base request](00-base-request.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [Custom operations](../controllers/04-custom-operations.md)
- [Index scopes](../../queries/01-index-scopes.md)
- [Base query](../../queries/00-base-query.md)
