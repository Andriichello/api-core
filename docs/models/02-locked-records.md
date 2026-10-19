# Locked records

`Core\Models\Traits\Lockable` freezes a record once it reaches some state, such as an invoice past its draft, and
`Core\Models\Traits\LockedByParent` freezes the records that are parts of it, such as the invoice's items. Use them
when a record must not change, or be deleted, after it was issued, sent or signed.

## Setup

A lockable model implements `Core\Models\Interfaces\LockableInterface` with the `Lockable` trait. It defines
`isLocked()`, judging by its attributes, and may list the attributes that can still change while it is locked:

```php
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Core\Models\Traits\Lockable;

class Invoice extends BaseModel implements LockableInterface
{
    use Lockable;

    public function isLocked(): bool
    {
        return $this->getAttribute('status') !== 'draft';
    }

    public function unlockedAttributes(): array
    {
        return ['status', 'notes', 'sent_at'];
    }
}
```

A part of a lockable record uses `LockedByParent` and names that record in `lockingParent()`:

```php
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Core\Models\Traits\LockedByParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends BaseModel
{
    use LockedByParent;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lockingParent(): (Model&LockableInterface)|null
    {
        return $this->getRelationValue('invoice');
    }
}
```

## How a locked record behaves

`Lockable` checks every update and delete of the model:

- **Unlocked before the change**: anything may change, including a change that locks the record. An invoice can become
  `ready` and store its totals in the same save.
- **Locked before and after the change**: only `unlockedAttributes()` and the timestamps (`created_at`, `updated_at`)
  may change. Changing any other attribute throws `RecordLockedException`, naming the attributes, and nothing is saved.
  `touch()` works.
- **Unlocked by the change**: the change is allowed as a whole. Returning an invoice to its draft may clear what it
  froze in the same save. Since this path lets everything through, guard whatever unlocks a record: limit the status
  changes with [status transitions](03-status-transitions.md) or a policy.
- **Deleting**: a locked record can't be deleted, softly or permanently, nor by setting its `deleted_at`.
- **Restoring**: a soft-deleted record, which is locked, can be restored, as it comes back as it was.

"Before the change" is the stored state: `wasLocked()` asks `isLocked()` of a copy of the model with its original
attributes, so unsaved changes don't decide it:

```php
$invoice->status;                     // 'ready'
$invoice->setAttribute('status', 'draft');

$invoice->wasLocked();                // true
$invoice->isLocked();                 // false
```

A new record isn't checked, so it may be created locked.

## How parts behave

A model with `LockedByParent` can't be created, updated or deleted while its locking parent is locked. On an update,
both the parent of the new attributes and the parent of the original ones are checked, so a part can be moved neither
out of a locked record nor into one.

The parents are loaded as they are stored: `lockingParent()` is called on a copy of the model without its loaded
relations (and with lazy loading allowed), so a parent loaded before it was locked doesn't let a change through. This
costs a query of the parent when a part is created, and two (the current and the original one) when it is updated or
deleted.

## Errors

`Core\Exceptions\RecordLockedException` extends Symfony's `ConflictHttpException`, so it answers with 409 and isn't
reported. It carries the locked record in `$record` (for a part, the locked parent) and, when attributes were refused,
their names in `$attributes`.

| Case | Message |
|------|---------|
| Changing a locked record | `The record is locked, so its total, number can't be changed.` |
| Deleting a locked record | `The record is locked, so it can't be deleted.` |
| Creating, changing or deleting a part | `The record, to which it belongs, is locked, so it can't be changed.` |

Changing a locked invoice's `total` through a CRUD controller:

```http
PATCH /api/invoices/12
Content-Type: application/json

{"total": 500}
```

answers with 409 and, outside debug mode:

```json
{
    "message": "The record is locked, so its total can't be changed."
}
```

## What isn't guarded

The checks run in Eloquent's model events (`updating`, `deleting`, `creating`), so, like other model events, they don't
see:

- updates and deletes of queries, such as `Invoice::query()->whereKey($id)->update([...])`;
- changes inside `Model::withoutEvents()`, including `saveQuietly()`, `updateQuietly()` and `deleteQuietly()`.

Use these only in code that is meant to bypass the lock, such as a migration that fixes data.

## Related

- [Status transitions](03-status-transitions.md)
- [Base model](00-base-model.md)
- [The CRUD repository](../repositories/01-crud-repository.md)
- [Errors](../http/02-errors.md)
- [CRUD policy](../http/policies/01-crud-policy.md)
