# Status transitions

`Core\Models\Traits\TransitionsStatus` lets a record's status change only along the transitions the model allows,
such as `draft → ready → sent → paid`. Use it for records with a workflow, and together with
[locked records](02-locked-records.md) to control what may unlock them.

## Setup

The model implements `Core\Models\Interfaces\TransitionsStatusInterface` with the trait, and maps each status to the
statuses it may change to:

```php
use App\Enums\InvoiceStatus;
use Core\Models\BaseModel;
use Core\Models\Interfaces\TransitionsStatusInterface;
use Core\Models\Traits\TransitionsStatus;

class Invoice extends BaseModel implements TransitionsStatusInterface
{
    use TransitionsStatus;

    protected $casts = ['status' => InvoiceStatus::class];

    public function statusTransitions(): array
    {
        return [
            InvoiceStatus::Draft->value => [InvoiceStatus::Ready],
            InvoiceStatus::Ready->value => [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Cancelled],
            InvoiceStatus::Sent->value => [InvoiceStatus::Paid, InvoiceStatus::Cancelled],
        ];
    }
}
```

The keys are raw status values (PHP doesn't allow enum cases as array keys); the targets may be enum cases or strings.
A status without a key, such as `paid` above, leads nowhere. The status is in the `status` column; override
`statusColumn()` to use another one:

```php
public function statusColumn(): string
{
    return 'stage';
}
```

## Behavior

- On every update that changes the status column, the stored status must lead to the new one. Otherwise
  `StatusTransitionException` is thrown and nothing is saved.
- An update that keeps the status, or doesn't touch it, isn't checked.
- A new record may be created with any status.
- Statuses are compared by their raw values, so a column with an enum cast may be given a case or its value, and the
  transitions may name either.
- A record whose stored status is `null` looks up the `''` key.

`canTransitionTo($status)` tells whether the stored status may change to the given one, ignoring unsaved changes. It
is `true` for the stored status itself:

```php
$invoice->status;                               // InvoiceStatus::Ready (stored)
$invoice->status = InvoiceStatus::Sent;         // not saved yet

$invoice->canTransitionTo(InvoiceStatus::Draft); // true
$invoice->canTransitionTo('ready');              // true, the stored status
$invoice->canTransitionTo(InvoiceStatus::Paid);  // false
```

Use it to decide what to offer, such as whether to show a "Send" button, or in a policy.

## Guarding locked records

[`Lockable`](02-locked-records.md) lets a change that unlocks a record through as a whole, so the transitions decide
whether it may happen. With an invoice locked past its draft and `status` among its `unlockedAttributes()`:

```php
$ready->update(['status' => 'draft', 'total' => 500]); // allowed: ready → draft unlocks it, the total changes too
$sent->update(['status' => 'draft', 'total' => 500]);  // StatusTransitionException: sent doesn't lead to draft
$sent->update(['total' => 500]);                       // RecordLockedException: the total is locked
```

## Errors

`Core\Exceptions\StatusTransitionException` extends Symfony's `ConflictHttpException`, so it answers with 409 and isn't
reported. It carries the record in `$record`, and the raw statuses in `$from` and `$to`. Its message names them, with
`none` for `null`:

```json
{
    "message": "The status can't change from sent to draft."
}
```

Like other model events, the check doesn't run for updates of queries (`Invoice::query()->update([...])`) or inside
`Model::withoutEvents()` and the `*Quietly()` methods.

## Related

- [Locked records](02-locked-records.md)
- [Enums](05-enums.md)
- [Base model](00-base-model.md)
- [Errors](../http/02-errors.md)
- [Base policy](../http/policies/00-base-policy.md)
