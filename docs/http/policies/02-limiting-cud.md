# Limiting changes

The `LimitsCUD` trait makes a policy read-only for everybody but super admins: creating, updating, deleting,
deleting permanently and restoring are denied to other users, whatever permissions their roles have. Use it for
records that the platform's own staff maintain and organizations only read.

## Usage

Add the trait, and its interface, to a policy that extends `CrudPolicy`:

```php
namespace App\Policies;

use App\Models\Project;
use Core\Models\BaseModel;
use Core\Policies\CrudPolicy;
use Core\Policies\Interfaces\LimitsCUDInterface;
use Core\Policies\Traits\LimitsCUD;

class ProjectPolicy extends CrudPolicy implements LimitsCUDInterface
{
    use LimitsCUD;

    protected BaseModel|string $model = Project::class;
}
```

## Behavior

The trait's `before()` returns `false` for the `create`, `update`, `delete`, `forceDelete` and `restore` abilities,
unless the user is a super admin. For any other ability, and for super admins, it calls `parent::before()`, so
`CrudPolicy`'s permission checks still apply to `viewAny`, `view` and custom abilities.

With an admin role that has `all` and `restore` on the model:

| Request                          | Admin | Super admin |
|----------------------------------|-------|-------------|
| `GET /api/projects`              | `200` | `200`       |
| `POST /api/projects`             | `403` | `201`       |
| `POST /api/projects/5/restore`   | `403` | `200`       |

So a role config that gives `all` on the model by mistake can't open it to changes. A denied request answers `403`
like any other (see [CRUD policy](01-crud-policy.md#how-a-request-is-authorized)).

The trait's method takes the place of `before()` of the class it is used in, and calls the parent's. A policy that
needs a `before()` of its own imports the trait's under another name and calls it:

```php
use LimitsCUD {
    before as protected limitedBefore;
}

public function before(mixed $user, string $ability): Response|bool|null
{
    // Checks of the policy's own come here
    return $this->limitedBefore($user, $ability);
}
```

`LimitsCUDInterface` declares `before()`, so code can check for the limit with `instanceof`.

## Related

- [CRUD policy](01-crud-policy.md)
- [Base policy](00-base-policy.md)
- [Roles](../../roles/00-overview.md)
- [Restoring](../controllers/03-restoring.md)
