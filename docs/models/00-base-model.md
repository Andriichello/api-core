# Base model

`Core\Models\BaseModel` is the Eloquent model that api-core's models and the application's own models extend. It
gives every model an organization id helper, a list of its columns for filters and sorts, and api-core's query
builder.

## What it provides

| Member | From | Description |
|--------|------|-------------|
| `orgId(): ?int` | `HasOrgId` | The record's `organization_id`, or `null`. |
| `orgContext()`, `hasNoOrg()`, `hasSameOrg()`, `hasTenantOrg()`, … | `HasOrgId` | Where the record belongs (see [Organization-scoped models](../organizations/03-organization-scoped-models.md)). |
| `listOfColumns()`, `listOfQueryableColumns()` | `ListsColumns` | The model's columns (see below). |
| `newEloquentBuilder()` | `BaseModel` | Returns a `Core\Queries\BaseQuery`, so `Model::query()` has its helpers. |

`BaseModel` implements `HasOrgIdInterface` and `ListsColumnsInterface`. All of api-core's models extend it, except
`Core\Models\AccessToken`, which extends Sanctum's `PersonalAccessToken`.

## Writing a model

Extend `BaseModel`, and give the model a query class of its own when it needs an index scope or query helpers:

```php
namespace App\Models;

use App\Queries\InvoiceQuery;
use Core\Models\BaseModel;
use Core\Models\Traits\BelongsToOrganization;

/**
 * Class Invoice.
 *
 * @method static InvoiceQuery query()
 */
class Invoice extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['client_id', 'number', 'status', 'total', 'notes'];

    public function newEloquentBuilder($query): InvoiceQuery
    {
        return new InvoiceQuery($query);
    }
}
```

`InvoiceQuery` extends `Core\Queries\BaseQuery` (see [Base query](../queries/00-base-query.md)). The traits of the
other documents in this folder (locking, status transitions, creator tracking, sequences) are added the same way.

## Columns

`ListsColumns` tells which columns a model has, judging by its definition rather than by the database:

- `listOfColumns(bool $guarded = false)`: the primary key, the `$fillable` columns, the guarded ones when asked for,
  and `created_at` and `updated_at` when the model uses timestamps. Hidden columns are included.
- `listOfQueryableColumns()`: the same list without the `$hidden` columns.

`CrudRepository` lets a request filter and sort on the queryable columns, unless the repository turns
`$allowAllFilters` or `$allowAllSorts` off. Hidden columns are left out on purpose: a filter or a sort on a password or
a token would reveal it one request at a time, without the value ever being returned. A column which isn't fillable
(such as `organization_id` or `deleted_at`) isn't queryable by default either; list it in the repository's
`$allowedFilters` or `$allowedSorts` to allow it (see [Filters](../querying/00-filters.md) and
[Sorting](../querying/04-sorting.md)).

## The morph map

`Core\Providers\MorphServiceProvider`, registered by package discovery, adds api-core's models to Eloquent's morph map
under aliases made of the last segment of the namespace and the plural kebab-case class name:

| Alias | Model |
|-------|-------|
| `models.access-tokens` | `Core\Models\AccessToken` |
| `models.audit-logs` | `Core\Models\AuditLog` |
| `models.files` | `Core\Models\File` |
| `models.organizations` | `Core\Models\Organization` |
| `models.sequences` | `Core\Models\Sequence` |
| `models.settings` | `Core\Models\Setting` |
| `models.users` | `Core\Models\User` |

The alias is what api-core writes wherever it names a model:

- polymorphic type columns, such as `files.fileable_type`, `settings.model_type`, `sequences.model_type`,
  `audit_logs.subject_type` and the `model_type` of spatie/laravel-permission's tables;
- permission names, such as `view models.users` (see [Roles](../roles/00-overview.md));
- the directories of stored files and the names of audit log entries (`models.users.updated`).

Permission names and file paths use the alias of a model in the map, or else a slug built the same way, so they read
`models.invoices` either way. The polymorphic columns and the names of audit log entries, though, use the full class
name of a model which isn't in the map. Register the application's models with `MorphServiceProvider::getMorphMap()`,
which builds the same aliases, so all of them agree and stored rows don't depend on class names:

```php
// app/Providers/AppServiceProvider.php
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Core\Providers\MorphServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    // models.clients, models.invoices, models.projects
    Relation::morphMap(MorphServiceProvider::getMorphMap([Client::class, Invoice::class, Project::class]));
}
```

Register every model that owns files, settings or sequences, or is audited. Choose the aliases before data is stored:
changing one later leaves the stored rows with the old type and renames the model's permissions, so the roles need to
be synced again.

A model whose namespace segment and class name match one of api-core's (such as `App\Models\File`) would get the same
alias, and the application's later registration would replace api-core's model under it. Give such a model an alias of
its own, e.g. `Relation::morphMap(['models.app-files' => File::class])`.

`MorphServiceProvider::slugOf($class, $plural = true)` and `slugWithFolderOf($class, $plural = true)` are public, so
the application can build the same slugs elsewhere.

## Related

- [Base query](../queries/00-base-query.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Filters](../querying/00-filters.md)
- [Sorting](../querying/04-sorting.md)
- [Roles and permissions](../roles/00-overview.md)
- [Tracking the creator](01-tracking-creator.md)
- [Locked records](02-locked-records.md)
- [Status transitions](03-status-transitions.md)
- [Sequences](04-sequences.md)
- [Enums](05-enums.md)
