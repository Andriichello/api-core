# Organization-scoped models

A model with the `Core\Models\Traits\BelongsToOrganization` trait belongs to an organization: its queries see only
the records of the [current organization](01-current-organization.md), and new records are created in it. Use it for
every model of a tenant, such as `Invoice` or `Client`; the `HasOrgId` helpers of every model are described below.

## Setup

Give the table an `organization_id` column, and the model the trait:

```php
// database/migrations/2026_01_01_000000_create_invoices_table.php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained();
    $table->string('number');
    $table->timestamps();
});
```

```php
namespace App\Models;

use Core\Models\BaseModel;
use Core\Models\Traits\BelongsToOrganization;

class Invoice extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['number'];
}
```

`organization_id` doesn't need to be fillable: it is filled in on saving.

## Behavior

**Queries are scoped.** A global scope named `organization` (`BelongsToOrganization::ORGANIZATION_SCOPE`) adds
`where invoices.organization_id = ?` with the current organization's id. Route model bindings, `find()` and the
relations to the model all go through it, so a record of another organization is simply not found. Validation rules
such as `exists:invoices,id` query the table directly, so they aren't scoped; add a `where` to them.

**The scope fails closed.** When no organization is current, the scope adds `where 1 = 0`, so queries find nothing,
rather than everything. A forgotten `organization` middleware, a job dispatched outside of an organization or a
console command that didn't call `run()` returns empty results, instead of leaking another tenant's records:

```php
CurrentOrganization::isSet();      // false
Invoice::query()->count();         // 0, whatever is in the table
```

**New records get the current organization.** A record saved without an `organization_id` gets the current one.
Without a current organization, saving it throws a `LogicException`
(`App\Models\Invoice has no organization, and none is current.`).

**Records stay in their organization.** Saving a record whose `organization_id` isn't the current organization
throws a `LogicException` (`... of another organization can't be saved in the current one.`), whether it is a new
record or an existing one.

**Without a current organization, a given one is kept.** Seeders, console commands and tests may save a record with
the organization they give it:

```php
(new Invoice)->forceFill(['number' => 'INV-1', 'organization_id' => $acme->id])->save();
```

The trait also adds an `organization()` `BelongsTo` relation.

## Working across organizations

Code that works with the records of several organizations, such as a report for super admins, removes the scope
explicitly, so it is visible in review:

```php
$counts = Invoice::query()
    ->withoutGlobalScope(Invoice::ORGANIZATION_SCOPE)
    ->selectRaw('organization_id, count(*) as invoices')
    ->groupBy('organization_id')
    ->pluck('invoices', 'organization_id');
```

Prefer `CurrentOrganization::run()` when the work is done per organization.

## The HasOrgId helpers

`Core\Models\BaseModel` implements `Core\Models\Interfaces\HasOrgIdInterface` with the `HasOrgId` trait, so every
model, scoped or not, answers these questions from its `organization_id`:

| Method                  | Returns                                                                      |
|-------------------------|------------------------------------------------------------------------------|
| `orgId()`               | The `organization_id`, or `null`. An `Organization` returns its own id.      |
| `orgContext()`          | `'tenant'`, an `OrgContext` case or `'unknown'` (see below).                 |
| `hasNoOrg()`            | Whether `organization_id` is `null`.                                         |
| `hasSameOrg($context)`  | Whether a model or an id has the same organization (two `null`s are equal).  |
| `hasTenantOrg()`        | Whether the record belongs to a tenant organization.                         |
| `hasInternalOrg()`      | Whether the record is internal.                                              |
| `hasGlobalOrg()`        | Whether the record is global.                                                |

```php
$user->hasSameOrg($invoice);   // true when both have the same organization_id
$user->hasInternalOrg();       // true for a user without an organization
```

## OrgContext

`Core\Enums\OrgContext` names the contexts of records which don't belong to a tenant:

| `organization_id` | `orgContext()`          | Meaning                                                       |
|-------------------|-------------------------|---------------------------------------------------------------|
| `null`            | `OrgContext::Internal`  | api-core's own records, such as a super admin without an organization. |
| `0`               | `OrgContext::Internal`  | Strictly for internal API or system operations.               |
| `-1`              | `OrgContext::Global`    | Shared with all tenants, read-only for them.                  |
| above `0`         | `'tenant'`              | A record of that organization.                                |
| below `-1`        | `'unknown'`             | No known context.                                             |

The contexts are labels: `BelongsToOrganization` matches the current organization's id only, so it doesn't show
global records to tenants, and a foreign key from `organization_id` to `organizations` rejects `0` and `-1`. A model
that shares records with all tenants writes its own scope for them.

## Related

- [Current organization](01-current-organization.md)
- [Resolving the organization](02-resolving-the-organization.md)
- [Acting organization](04-acting-organization.md)
- [Building a feature](../getting-started/06-building-a-feature.md)
- [Base model](../models/00-base-model.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Async jobs](../jobs/00-async-jobs.md)
