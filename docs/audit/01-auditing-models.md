# Auditing models

The `Audited` trait records a model's changes in the audit log: every create, update, delete and restore becomes an
entry with the old and new values of the attributes. Use it on the models, whose history the app must keep.

## Setup

```php
namespace App\Models;

use Core\Models\BaseModel;
use Core\Models\Traits\Audited;
use Core\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use Audited;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $hidden = ['access_code'];

    public function auditExcluded(): array
    {
        return ['search_text'];
    }
}
```

## Events

| Event | When | `changes` |
|---|---|---|
| `created` | The model is created. | `new`: its attributes. |
| `updated` | The model is updated. | `old` and `new` values of the changed attributes only. |
| `deleted` | The model is deleted, softly when it can be. | `old`: its attributes. |
| `restored` | A soft-deleted model is restored. | `null` |
| `force_deleted` | A soft-deletable model is deleted permanently. | `old`: its attributes. |

A restore is recorded once, as `restored`, not as an update of `deleted_at`. A model without `SoftDeletes` records its
deletes as `deleted`.

For example, an update of an invoice's total and status records:

```json
{
    "old": {"total": 1200, "status": "draft"},
    "new": {"total": 1500, "status": "sent"}
}
```

## What is recorded of the attributes

- The values are the raw ones, as the model holds them for the database (a JSON column as its text, a date as its
  stored string), not as casts or accessors present them.
- The key, the timestamps, `deleted_at` and the attributes, which `auditExcluded()` lists, are left out.
- Hidden attributes (`$hidden`) are recorded as `[hidden]` (`Audited::HIDDEN_VALUE`), so the log tells that a password
  or a token changed without keeping it.
- An update, which changes nothing but left-out attributes, such as `touch()`, records nothing.

## Who, where and what

- The causer is the signed-in user, or none, when there is none, as in a console command or a queued job.
- The organization is the model's `orgId()` (its `organization_id`, unless the model overrides `orgId()`), else the
  current organization, else the causer's.
- The name is the model's morph alias and the event, such as `models.invoices.updated`.
- The model is the entry's subject, and `auditLogs()` gives the model's entries:

```php
$history = $invoice->auditLogs()->latest('id')->get();
```

## Transactions

An entry is written from the model's event, while the change is being saved. When the change runs in a transaction
and it rolls back, its entry is rolled back with it, so the log never shows a change, which didn't happen:

```php
DB::transaction(function () use ($invoice) {
    $invoice->update(['status' => 'sent']);
    $invoice->lines()->create(['description' => 'Consulting', 'amount' => 300]);

    // An exception up to here leaves neither the update nor its entry
});
```

## What isn't audited

Only changes made through model instances fire the events, which the trait listens to. Updates and deletes through a
query, such as `Invoice::query()->where('status', 'draft')->update(['status' => 'void'])`, and inserts with
`insert()`, record nothing. Record such bulk changes with `AuditLog::record()` (see
[Recording events](03-recording-events.md)).

## Related

- [Audit log](00-overview.md)
- [Recording events](03-recording-events.md)
- [Reading and pruning](04-reading-and-pruning.md)
- [Tracking the creator](../models/01-tracking-creator.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Current organization](../organizations/01-current-organization.md)
