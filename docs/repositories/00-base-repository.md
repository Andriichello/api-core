# Base repository

`Core\Repositories\BaseRepository` ties a repository to a model class and gives it a query of that model. Extend it for
a repository that reads or writes a model outside the CRUD flow; extend `CrudRepository`, which builds on it, for the
repository of a CRUD controller.

## What it provides

`BaseRepository` is abstract and implements `Core\Repositories\Interfaces\BaseRepositoryInterface`:

| Member | Description |
|--------|-------------|
| `protected BaseModel\|string $model` | The model class (or an instance of it). Defaults to `BaseModel::class`; every repository sets its own. |
| `model(): BaseModel\|string` | The `$model` as it was set. |
| `instance(): ?BaseModel` | A new, unsaved instance of the model class. |
| `builder(): BaseQuery` | `$model::query()`: a new query of the model, with its global scopes. |
| `isSoftDeletable(): bool` | Whether the model uses `SoftDeletes` (directly, through a parent class or another trait). |

## Usage

```php
namespace App\Repositories;

use App\Models\Invoice;
use Core\Models\BaseModel;
use Core\Repositories\BaseRepository;

class InvoiceTotalsRepository extends BaseRepository
{
    protected BaseModel|string $model = Invoice::class;

    public function totalOfClient(int $clientId): int
    {
        return (int) $this->builder()
            ->where('client_id', $clientId)
            ->sum('total');
    }
}
```

`builder()` here applies no index scope and reads no request: it is the model's plain query, limited only by its
global scopes (such as `BelongsToOrganization`'s). `CrudRepository::builder()` adds the request's index scope, filters,
sorts and includes on top of it.

## The repositories api-core ships

| Repository | Base | Description |
|------------|------|-------------|
| `UserRepository` | `CrudRepository` | Users: roles, inviting, re-inviting deleted users. |
| `FileRepository` | `CrudRepository` | Files, with their filters and sorts; files are added with `HasFiles`, not `create()`. |
| `SettingRepository` | `CrudRepository` | Settings; read and changed with `HasSettings`, not the repository's methods. |
| `AccessTokenRepository` | none | Access and refresh tokens. |
| `RoleRepository` | none | Syncing the roles with `api-core.roles`. |
| `PermissionRepository` | none | Creating the permissions of a resource. |

## Related

- [The CRUD repository](01-crud-repository.md)
- [Eager loads](02-eager-loads.md)
- [Base query](../queries/00-base-query.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Syncing roles](../roles/02-syncing-roles.md)
- [Tokens](../auth/02-tokens.md)
- [Managing users](../users/01-managing-users.md)
