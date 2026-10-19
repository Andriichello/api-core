# Syncing roles

`Core\Database\Seeders\RolesAndPermissionsSeeder` makes the roles and permissions in the database match
`api-core.roles`. Run it on installation, and again whenever the list changes; it is safe to run any number of times.

## Setup

Call the seeder from the application's `DatabaseSeeder`:

```php
namespace Database\Seeders;

use Core\Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
```

Or run it alone, for example in a deployment after a change of the roles:

```bash
php artisan db:seed --class="Core\Database\Seeders\RolesAndPermissionsSeeder" --force
```

## What it does

The seeder calls `Core\Repositories\RoleRepository::sync()`, which:

1. forgets spatie's cached permissions;
2. creates the `super-admin` role when it is missing, and takes away any permission it has;
3. for every role of `api-core.roles`, creates the role and the permissions of its actions when they are missing,
   and gives the role exactly those permissions, taking away the ones it had beyond them;
4. forgets the cached permissions again, so the new ones apply right away.

It changes nothing else:

- Users keep their roles, and their abilities change with the permissions of those roles at once.
- A role removed from the list isn't deleted: its users keep it, with the permissions it had. Delete it yourself
  when nobody should have it.
- Permissions that no role lists any more aren't deleted, nor are permissions given to users directly.

Nothing is truncated, so it never detaches roles from users, as a seeder that recreates the tables would.

## RoleRepository

`RoleRepository` reads the roles from the config on every call, so it always follows the current list:

| Method                      | Returns                                                                           |
|-----------------------------|-----------------------------------------------------------------------------------|
| `definitions(): array`      | The entries of `api-core.roles` (`name`, `permissions`), from the highest.        |
| `names(): array`            | `super-admin` followed by the configured names, from the highest to the lowest.   |
| `rankOf(string $role): ?int`| The role's position in `names()`, `0` for `super-admin`, `null` for a role that isn't defined. |
| `sync(): void`              | Syncs the database, as described above.                                           |

Resolve it from the container:

```php
use Core\Repositories\RoleRepository;

app(RoleRepository::class)->names(); // ['super-admin', 'owner', 'accountant', 'viewer']
```

## PermissionRepository

`Core\Repositories\PermissionRepository` creates the permissions of a resource:

```php
use App\Models\Invoice;
use Core\Repositories\PermissionRepository;

$permissions = app(PermissionRepository::class)->create(Invoice::class, 'all', 'send');
// Permission models: viewAny, view, create, update and delete models.invoices, and send models.invoices
```

`create(string $resource, string ...$permissions)` names each permission `{action} {morph}` with the resource's morph
(see [Roles](00-overview.md#permission-names)), creates the missing ones and returns all of them.
`actionsFrom(string ...$permissions)` expands `all` into `viewAny`, `view`, `create`, `update` and `delete`, and drops
duplicates.

## In tests

Sync the roles in a test's `setUp()`, after changing the config if the test needs other roles:

```php
protected function setUp(): void
{
    parent::setUp();

    config(['api-core.roles' => [['name' => 'owner', 'permissions' => [Invoice::class => 'all']]]]);

    app(RoleRepository::class)->sync();
}
```

`$this->seed(RolesAndPermissionsSeeder::class)` does the same.

## Related

- [Roles](00-overview.md)
- [Configuring roles](01-configuring-roles.md)
- [Ranking roles](03-ranking-roles.md)
- [Installation](../getting-started/02-installation.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
