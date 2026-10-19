# Restoring

The `restore` operation of a CRUD controller brings a soft-deleted model back. It is opt-in twice: a controller handles
it only when it lists it in `$operations`, and only the roles that list the `restore` permission (and super admins)
may use it.

## Setup

1. Make the model soft-deletable, with Laravel's `SoftDeletes` trait and a `deleted_at` column.

2. Add the operation to the controller:

   ```php
   use Core\Http\Requests\Crud\RestoreRequest;

   protected array $operations = [
       'index' => IndexRequest::class,
       'show' => ShowRequest::class,
       'store' => StoreInvoiceRequest::class,
       'update' => UpdateInvoiceRequest::class,
       'destroy' => DestroyRequest::class,
       'restore' => RestoreRequest::class,
   ];
   ```

3. Register its route:

   ```php
   Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore');
   ```

4. Give the roles that may restore the `restore` action in `api-core.roles`, and sync them with
   `RolesAndPermissionsSeeder`. `all` stands for `viewAny`, `view`, `create`, `update` and `delete` only:

   ```php
   [
       'name' => 'admin',
       'permissions' => [
           Invoice::class => ['all', 'restore'],
       ],
   ],
   ```

## Usage

```http
POST /api/invoices/5/restore
Authorization: Bearer {access_token}
```

```json
{
    "data": {
        "id": 5,
        "number": "INV-0005",
        "deleted_at": null
    },
    "message": "Restored"
}
```

The response holds the restored model, read again from the database.

## Behavior

- **`RestoreRequest`** has the `restore` ability, and finds its target only among the soft-deleted models
  (`whereTrashed()`), within the user's index scope.
- **Only deleted models are found.** Restoring a model that isn't deleted, one the user can't see, or one of a model
  without soft deletes, answers 404.
- **The policy decides.** `CrudPolicy::restore()` lets any user with a role other than `guest` restore, once the
  permission is met. `UserPolicy` refuses to restore a user who ranks above the signed-in one, and a policy with
  `LimitsCUD` leaves restoring, like creating, updating and deleting, to super admins.
- **Parents apply.** On a nested route, only the parents' deleted models are found.
- **Without the operation**, `restore()` still exists on every `CrudController`, but calling it throws a
  `BadMethodCallException` (500), so don't register the route for a controller that doesn't list it.

Deleting permanently is the other operation on deleted models: `DELETE /api/invoices/5?force=1` finds the invoice even
when it's soft-deleted, and needs the `forceDelete` permission, which `all` doesn't include either (see
[CRUD requests](../requests/01-crud-requests.md)).

## Errors

| Status | When |
|---|---|
| 403 | The user's roles don't have the `restore` permission, or the policy refuses |
| 404 | The model isn't soft-deleted, doesn't exist, or the user can't see it: `Not found.` |
| 500 | The controller doesn't list the `restore` operation |

## Related

- [CRUD controller](01-crud-controller.md)
- [CRUD requests](../requests/01-crud-requests.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [Limiting CUD](../policies/02-limiting-cud.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
- [Syncing roles](../../roles/02-syncing-roles.md)
- [CRUD repository](../../repositories/01-crud-repository.md)
- [Base query](../../queries/00-base-query.md)
