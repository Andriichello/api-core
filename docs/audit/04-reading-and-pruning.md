# Reading and pruning the audit log

api-core has no routes of the audit log: an app reads it with `AuditLogQuery`, which limits the entries to what a user
may see, and exposes it as it needs. Old entries are deleted by Laravel's `model:prune`, once a retention is set.

## Reading

`AuditLog::query()` is an `AuditLogQuery`. Its `index($user)` scope gives the entries a user may see:

| User | Entries |
|---|---|
| A user of an organization | The entries of the organization, in which the user acts (`actingOrgId()`). |
| A super admin, who acts in a particular organization | That organization's entries. |
| A super admin of no organization, who acts in all of them | All entries, those of no organization included. |
| Any other user of no organization, or a guest | None. |

`ofSubject($model)` limits the query to the entries of one record, and the `Audited` trait's `auditLogs()` relation
gives the same entries from the model:

```php
$entries = AuditLog::query()
    ->index($user)
    ->ofSubject($invoice)
    ->latest('id')
    ->get();
```

The index scope decides which organization's entries a user sees, not whether the user may read the log at all; check
that separately, as with a role. A minimal read-only route of the log:

```php
namespace App\Http\Controllers;

use Core\Http\Controllers\ResourceController;
use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends ResourceController
{
    protected string $resourceClass = BaseResource::class;

    protected string $collectionClass = BaseCollection::class;

    public function index(Request $request): ApiResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isSuperAdmin(), 403);

        return $this->asPaginatedResponse(AuditLog::query()->index($user)->latest('id'), $request);
    }
}
```

Register it under the `organization` middleware, so a super admin can pick the organization with `X-Organization-Id`.
For filters, sorts and includes, build a CRUD controller with only the `index` and `show` operations instead (see
[CRUD controller](../http/controllers/01-crud-controller.md)).

## Retention

`api-core.audit.retention_days` (`API_CORE_AUDIT_RETENTION_DAYS`) is the number of days, for which entries are kept.
Unset, it is `null`, and entries are kept forever.

```env
API_CORE_AUDIT_RETENTION_DAYS=365
```

## Pruning

`AuditLog` is mass-prunable: `model:prune` deletes the entries older than the retention, in chunks of queries, without
loading them, which is also why the guard against deleting single entries doesn't stop it. Without a retention, it
deletes nothing.

```bash
php artisan model:prune --model="Core\Models\AuditLog"
```

The model must be named, since `model:prune` looks for prunable models only in the app's own `app/Models`. Schedule it
in `routes/console.php`:

```php
use Core\Models\AuditLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [AuditLog::class]])->daily();
```

`--pretend` shows how many entries would be deleted, without deleting them.

## Related

- [Audit log](00-overview.md)
- [Auditing models](01-auditing-models.md)
- [Scheduled tasks](../getting-started/05-scheduled-tasks.md)
- [Acting organization](../organizations/04-acting-organization.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Paginated responses](../pagination/01-paginated-responses.md)
- [Ranking roles](../roles/03-ranking-roles.md)
