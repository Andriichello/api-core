# CRUD requests

The requests of the CRUD operations, one per operation, which a CRUD controller lists in `$operations`. They name the
ability the policy checks, find the target model, and refuse bodies that can't be saved. An application extends
`StoreRequest` and `UpdateRequest` with the rules of its models' fields.

## CrudRequest

`Core\Http\Requests\CrudRequest` extends `BaseRequest` with:

- `$ability` and `getAbility()`: the ability the controller's policy checks. A request without one (`null`, the
  default of `CrudRequest`) isn't checked by the policy at all.
- `getParents()`, `getParent(string $name)` and `setParents(array $parents)`: the parent models of a nested route,
  keyed by their route parameters, which the controller resolves
  (see [Nested routes](../controllers/02-nested-routes.md)).

## The requests

All of them are in `Core\Http\Requests\Crud`:

| Request | Ability | Target | Body |
|---|---|---|---|
| `IndexRequest` | `viewAny` | — | — |
| `ShowRequest` | `view` | `{id}` | — |
| `StoreRequest` | `create` | — | An object of the fields |
| `UpdateRequest` | `update` | `{id}` | An object with at least one field it can update |
| `DestroyRequest` | `delete`, or `forceDelete` with `force` | `{id}`, soft-deleted too with `force` | `force` (boolean) |
| `RestoreRequest` | `restore` | `{id}`, soft-deleted only | — |

The ones with a target implement `WithTargetInterface` (see [Targets](02-targets.md)): the policy checks them against
the model of `{id}`, found within the user's index scope.

## Store requests

```php
namespace App\Http\Requests\Invoices;

use Core\Http\Requests\Crud\StoreRequest;

class StoreInvoiceRequest extends StoreRequest
{
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:32'],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

The controller saves `validated()`, so a field without a rule is never saved. `StoreRequest` itself has no rules, so a
controller that keeps it saves none of the body's fields.

## Update requests

The fields of an update are optional, so their rules start with `sometimes`:

```php
class UpdateInvoiceRequest extends UpdateRequest
{
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'string', 'max:32'],
            'issued_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

After validation, `UpdateRequest` refuses an update that would change nothing, yet answer `200 Updated`: one whose
body sends none of the fields its rules know.

```http
PATCH /api/invoices/5
Content-Type: application/json

{"nickname": "Q3", "age": 30}
```

```json
{
    "message": "None of the given fields can be updated: nickname, age.",
    "errors": {
        "fields": ["None of the given fields can be updated: nickname, age."]
    }
}
```

An empty body (`{}`), or one with only `id`, answers `The request has no fields to update.` on `fields`. Only the keys
the client sent count, not values that `defaults()` or `overrides()` added; a sent field counts even when `overrides()`
rewrites it, and so does a field that has only nested rules, such as `points` for `points.*.lat`. To allow empty
updates, override `ensureFieldsToUpdate()` with an empty method.

## Bodies that aren't objects

`StoreRequest` and `UpdateRequest` use `WithObjectBody`: a JSON body that is a list or a single value, such as
`[{"number": "INV-1"}]`, can't be mapped onto a model's fields, so it answers 422 on `body`:
`The body must be a JSON object of the fields to save, such as {"name": "…"}.` An empty list counts as an empty
object. Form bodies aren't checked. See [Request traits](03-request-traits.md).

## Destroy requests

`force` is read from the query or the body (`DELETE /api/invoices/5?force=1`). With it, `getAbility()` returns
`forceDelete`, the target is found even when it's soft-deleted, and the controller deletes it permanently with the
repository's `forceDelete()`, which runs `delete()` first, so the model's deleting logic runs too. `force()` tells
whether it's asked for. `forceDelete`, like `restore`, isn't part of the `all` action of the roles' config.

## Restore requests

`RestoreRequest` finds its target only among soft-deleted models. See [Restoring](../controllers/03-restoring.md).

## Requests of custom operations

A custom operation's request extends the request whose target and checks fit, and names its ability:

```php
use Core\Http\Requests\Crud\ShowRequest;

class SendInvoiceRequest extends ShowRequest
{
    protected ?string $ability = 'send';
}
```

The policy then needs a `send()` method, and the roles the `send` action. See
[Custom operations](../controllers/04-custom-operations.md).

## Related

- [Base request](00-base-request.md)
- [Targets](02-targets.md)
- [Request traits](03-request-traits.md)
- [CRUD controller](../controllers/01-crud-controller.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
- [Errors](../02-errors.md)
