# CRUD policy

`Core\Policies\CrudPolicy` authorizes the requests of a `CrudController`: a user needs the permission of the
request's ability, and the ability's method decides after that. Write one policy per model that has CRUD routes.

## Writing a policy

```php
namespace App\Policies;

use App\Models\Invoice;
use Core\Models\BaseModel;
use Core\Policies\CrudPolicy;

class InvoicePolicy extends CrudPolicy
{
    protected BaseModel|string $model = Invoice::class;
}
```

The controller takes it in its constructor, so the container injects it:

```php
public function __construct(InvoicePolicy $policy, InvoiceRepository $repository)
{
    parent::__construct($policy, $repository);
}
```

A `FileController` or a `SettingController` takes the policy of the model whose files or settings it handles (see
[File routes](../../files/02-file-routes.md) and [Setting routes](../../settings/02-setting-routes.md)).

## How a request is authorized

`CrudController` calls `determine($request)` before it validates the request:

1. The ability comes from the request (`getAbility()`). A request without one is allowed.
2. `before()` checks the permission: unless the user is a super admin, the user needs the `{ability} {morph}`
   permission (see [Base policy](00-base-policy.md)). A request without a user has no permissions, so it is denied,
   `index` and `show` included.
3. A request with a target (`show`, `update`, `destroy`, `restore`) looks the target up within the user's index scope
   (see [Targets](../requests/02-targets.md)). A model the user can't list answers `404`, not `403`.
4. The ability's method decides, with the user and the target.

A denied request answers `403` with `{"message": "This action is unauthorized."}`.

## Abilities

| Operation          | Request          | Ability       | Permission                    | The method allows        |
|--------------------|------------------|---------------|-------------------------------|--------------------------|
| `index`            | `IndexRequest`   | `viewAny`     | `viewAny models.invoices`     | Everybody                |
| `show`             | `ShowRequest`    | `view`        | `view models.invoices`        | Everybody                |
| `store`            | `StoreRequest`   | `create`      | `create models.invoices`      | Any role but `guest`     |
| `update`           | `UpdateRequest`  | `update`      | `update models.invoices`      | Any role but `guest`     |
| `destroy`          | `DestroyRequest` | `delete`      | `delete models.invoices`      | Any role but `guest`     |
| `destroy` (`force`)| `DestroyRequest` | `forceDelete` | `forceDelete models.invoices` | Any role but `guest`     |
| `restore`          | `RestoreRequest` | `restore`     | `restore models.invoices`     | Any role but `guest`     |

"Any role but `guest`" is `hasAnyRole($user)`: a user whose only role is `guest` reads, but changes nothing, even with
a `create` permission. Super admins need no permissions, and their role passes the methods.

`DELETE /api/invoices/7?force=1` deletes permanently, which is the `forceDelete` ability of its own. In the roles
config, `all` stands for `viewAny`, `view`, `create`, `update` and `delete`, but not for `restore` and `forceDelete`:
a role that may restore or delete permanently lists them, or else only super admins may (see
[Configuring roles](../../roles/01-configuring-roles.md)).

```php
'permissions' => [
    Invoice::class => ['all', 'restore', 'forceDelete'],
],
```

## Customizing

Override an ability's method to add conditions on the target; keep the parent's check:

```php
public function update(mixed $user, mixed $model): bool
{
    return parent::update($user, $model) && $model->status === 'draft';
}
```

An extra operation of a controller (see [Custom operations](../controllers/04-custom-operations.md)) has a request with
an ability of its own, such as `send`. Its permission (`send models.invoices`) is checked by `before()` like any other,
and a method of the same name decides, with the user and, when the request has a target, the target. An ability
without a method is denied.

```php
public function send(mixed $user, mixed $model): bool
{
    return $this->hasAnyRole($user) && $model->status === 'draft';
}
```

An override of `before()` calls `parent::before()`, which checks the permissions.

## Related

- [Base policy](00-base-policy.md)
- [Limiting changes](02-limiting-cud.md)
- [User policy](03-user-policy.md)
- [CRUD controller](../controllers/01-crud-controller.md)
- [CRUD requests](../requests/01-crud-requests.md)
- [Restoring](../controllers/03-restoring.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
