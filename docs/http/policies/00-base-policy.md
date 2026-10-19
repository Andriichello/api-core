# Base policy

`Core\Policies\BasePolicy` is the base of api-core's policies, and `ChecksPermissions` maps their abilities to the
permissions of spatie/laravel-permission. Extend `CrudPolicy`, which combines both, for the policies of an application
(see [CRUD policy](01-crud-policy.md)); read this document to know what happens underneath.

## BasePolicy

A policy names the model it controls in `$model`; `getModel()` returns it, or `null` when it isn't set.

| Method                                     | Description                                                                 |
|--------------------------------------------|-----------------------------------------------------------------------------|
| `before($user, $ability)`                  | Runs before every ability; `null` lets the ability's method decide.         |
| `__call($name, $arguments)`                | Handles an ability the policy has no method for, through `determineMissing()`. |
| `determineMissing($ability, ...$args)`     | Decides such an ability: `false`, unless overridden.                        |

An ability without a method is denied, even for a super admin, rather than failing with an error. To allow abilities
that have no method of their own, override `determineMissing()`:

```php
public function determineMissing(string $ability, mixed ...$args): bool
{
    return $ability === 'export' && $this->arePermissionsMet($args[0] ?? null, 'export');
}
```

## How permissions map to abilities

`ChecksPermissions` names the permission of an ability `{ability} {morph}`, where the morph is the model's alias in
the morph map, or else a slug of its namespace and class (see [Base model](../../models/00-base-model.md)):

| Model                  | Ability    | Permission                |
|------------------------|------------|---------------------------|
| `Core\Models\User`     | `update`   | `update models.users`     |
| `App\Models\Invoice`   | `viewAny`  | `viewAny models.invoices` |
| `App\Models\Invoice`   | `send`     | `send models.invoices`    |

`PermissionRepository::create(Invoice::class, 'all')` creates such permissions, and `RolesAndPermissionsSeeder`
creates and gives them by the roles config (see [Configuring roles](../../roles/01-configuring-roles.md)).

| Method                                                | Description                                                       |
|-------------------------------------------------------|-------------------------------------------------------------------|
| `shouldCheckPermissions($user, $ability = null)`      | `false` for a super admin; otherwise `true` when the policy has a model. |
| `arePermissionsMet($user, $abilities, $resource = null)` | `true` when the user has the permission of every ability.      |
| `arePermissionsDefined($abilities, $resource = null)` | `true` when the permission of every ability exists.               |
| `isMissingPermissionMet($user, $permission)`          | Protected: whether a permission that doesn't exist is met.        |

`$abilities` is one ability or a list, and `$resource` names another model class than the policy's:

```php
$this->arePermissionsMet($user, ['view', 'update'], Client::class); // view models.clients, update models.clients
```

## Behavior

- A request without a user meets no permission.
- A permission that doesn't exist in the database is met by super admins only, through `isMissingPermissionMet()`.
  So a model that the roles config doesn't mention is closed to everybody else, rather than open, and a typo in a
  permission denies instead of allowing.
- Super admins skip the permission checks altogether (`shouldCheckPermissions()` is `false` for them).
- Permissions are read from spatie's cached registry (`PermissionRegistrar`), so checking them doesn't query the
  database each time.

## Related

- [CRUD policy](01-crud-policy.md)
- [Limiting changes](02-limiting-cud.md)
- [User policy](03-user-policy.md)
- [Roles](../../roles/00-overview.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
- [Syncing roles](../../roles/02-syncing-roles.md)
