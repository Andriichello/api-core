# Configuring roles

An application defines its roles and their permissions in `api-core.roles`, from the highest to the lowest, and
`RolesAndPermissionsSeeder` creates them in the database. Change this list whenever the roles or the models of the
application change.

## The list

Publish the config with `php artisan vendor:publish --tag=api-core-config`, and list the roles in
`config/api-core.php`:

```php
use App\Models\Client;
use App\Models\Invoice;
use Core\Models\Organization;
use Core\Models\User;

return [
    'roles' => [
        [
            'name' => 'owner',
            'permissions' => [
                Invoice::class => ['all', 'restore', 'send'],
                Client::class => 'all',
                User::class => ['all', 'attachRole', 'detachRole'],
                Organization::class => ['viewAny', 'view', 'update'],
            ],
        ],
        [
            'name' => 'accountant',
            'permissions' => [
                Invoice::class => ['viewAny', 'view', 'create', 'update', 'send'],
                Client::class => ['viewAny', 'view'],
                User::class => ['viewAny', 'view'],
            ],
        ],
        [
            'name' => 'viewer',
            'permissions' => [
                Invoice::class => ['viewAny', 'view'],
                Client::class => ['viewAny', 'view'],
            ],
        ],
    ],
];
```

Each role has a `name` and, optionally, `permissions`: a map of model classes to an action or a list of actions.
The roles rank in the order of the list, below the built-in `super-admin`, which isn't listed (see
[Ranking roles](03-ranking-roles.md)).

## Actions

| Action          | Grants                                                                                     |
|-----------------|--------------------------------------------------------------------------------------------|
| `viewAny`       | Listing the models (`GET /api/invoices`).                                                  |
| `view`          | Showing one (`GET /api/invoices/{id}`).                                                    |
| `create`        | Creating one.                                                                              |
| `update`        | Updating one.                                                                              |
| `delete`        | Deleting one (soft-deleting, for a soft-deletable model).                                  |
| `all`           | The five above: `viewAny`, `view`, `create`, `update` and `delete`.                        |
| `restore`       | Restoring a soft-deleted one, where the controller handles `restore`. Not part of `all`.   |
| `forceDelete`   | Deleting one permanently (`DELETE` with `force`). Not part of `all`.                       |
| anything else   | A custom action, such as `send`: the ability of a custom operation, which the policy checks before calling its method of that name (see [Custom operations](../http/controllers/04-custom-operations.md)). |

`all` covers the five everyday actions only: a role may restore or delete permanently only when it lists `restore`
or `forceDelete`. The seeder creates only the permissions that roles list, and a permission that doesn't exist is met
only by super admins, so while no role lists them, only super admins may restore or delete permanently.

## The list replaces the defaults

`api-core.roles` is a list, so the application's list replaces api-core's default `admin`, `user` and `guest` as a
whole; it isn't merged with them. List every role the application needs. The `super-admin` is added by api-core
whatever the list holds.

## Permissions api-core checks

- The CRUD actions above, by `CrudPolicy`, on the controller's model. `UserController` needs `viewAny`, `view`,
  `create` (inviting), `update` (updating and resending an invitation), `delete` and `forceDelete` on `User`.
- `attachRole` and `detachRole` on `User`, by `UserPolicy`, to add roles to users and to remove them.

The default `admin` role also holds `attachAnyRole` on `User`, and `attachUser`, `attachAnyUser` and `detachUser` on
`Role`. api-core creates them, but doesn't check them; they are there for an application's own checks.

## After a change

Ranks follow the config right away, since they are read from it on every check. Permissions are stored in the
database, so run the seeder again after every change of the list (see [Syncing roles](02-syncing-roles.md)):

```bash
php artisan db:seed --class="Core\Database\Seeders\RolesAndPermissionsSeeder"
```

## Related

- [Roles](00-overview.md)
- [Syncing roles](02-syncing-roles.md)
- [Ranking roles](03-ranking-roles.md)
- [Configuration](../getting-started/03-configuration.md)
- [CRUD policy](../http/policies/01-crud-policy.md)
- [Restoring](../http/controllers/03-restoring.md)
- [Managing users](../users/01-managing-users.md)
