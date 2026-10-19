# Sequences

`Core\Models\Sequence` allocates numbers from sequences without gaps, such as the numbers of an organization's
invoices. Numbers are allocated under a lock of the sequence's row, so two requests never get the same number, and
within the caller's transaction, so a rolled back record gives its number back. `HasSequences` adds shortcuts to the
models that own sequences.

## Owners and keys

A sequence belongs to a saved model, its owner, and has a key, which is any string the application chooses: one
sequence per key and owner. The key decides how numbering restarts, such as `invoices` (one sequence for good),
`invoices:2026` (one per year) or `invoices:client:5:2026` (per client and year). Sequences are kept in the `sequences`
table (`model_type`, `model_id`, `key`, `next_number`, unique per owner and key). Every sequence starts at 1.

The number is just an integer: its format, such as `INV-2026-00042`, is the application's.

Register the owner's model in the morph map (see [Base model](00-base-model.md#the-morph-map)), so `model_type`
stores its alias rather than its class name. api-core's `Organization` is registered already.

## Usage

`Sequence` works with any saved model. Allocate inside the transaction that saves the numbered record (the `3` is
explained below):

```php
use Core\Helpers\CurrentOrganization;
use Core\Models\Sequence;
use Illuminate\Support\Facades\DB;

$invoice = DB::transaction(function () use ($attributes) {
    $organization = CurrentOrganization::get();
    $year = now()->year;

    $number = Sequence::next($organization, "invoices:$year");

    return Invoice::query()->create([
        ...$attributes,
        'number' => sprintf('INV-%d-%05d', $year, $number),
    ]);
}, 3);
```

| Method | Description |
|--------|-------------|
| `Sequence::next($owner, $key): int` | Allocates the next number, creating the sequence at 1 when it doesn't exist. |
| `Sequence::setNext($owner, $key, $number): void` | Sets the number to allocate next, such as to continue another system's numbering. |
| `Sequence::peek($owner, $key): int` | The number `next()` would allocate now, without allocating it. |

A model that owns sequences can use the `HasSequences` trait, which adds the `sequences()` relation (a `MorphMany`),
`nextInSequence($key)` and `setNextInSequence($key, $number)`:

```php
use Core\Models\BaseModel;
use Core\Models\Traits\HasSequences;

class Client extends BaseModel
{
    use HasSequences;
}

$number = $client->nextInSequence('invoices:' . now()->year);
```

Deleting a model with `HasSequences` deletes its sequences. A soft-deleted model keeps them until it is deleted
permanently, so it continues its numbering when restored.

## How a number is allocated

`next()` runs in a transaction, which is retried up to 3 times (`Sequence::ATTEMPTS`) when it deadlocks:

1. It selects the sequence's row with `SELECT … FOR UPDATE`, which locks it.
2. When there is no row, it creates one with `insertOrIgnore` (a concurrent allocation may create it first) and
   selects it again, locked.
3. It returns `next_number` and stores the number after it.

The row is locked before the number is read, so a concurrent allocation waits for the lock and then reads the number
after the committed one. Without the lock, both would read the same number.

Called inside the caller's transaction, `next()` runs in a savepoint and the lock is held until the outer transaction
ends. So:

- **No gaps**: when the outer transaction rolls back, such as when saving the invoice fails, the allocation is rolled
  back too and the number is allocated again by the next call. Allocate inside the transaction that saves the numbered
  record; called on its own, `next()` commits at once and a failure after it leaves a gap.
- **Short transactions**: other allocations of the same sequence wait until the outer transaction commits, so do the
  slow work (emails, files) after it.
- **Deadlocks**: two first allocations of a new sequence may deadlock while they create its row. Laravel retries only
  an outermost transaction, so inside the caller's transaction the deadlock is thrown out of it; give that transaction
  attempts of its own, such as `DB::transaction($callback, 3)`.

SQLite has no row locks and ignores `FOR UPDATE`. MySQL and PostgreSQL honor it; api-core's `SequenceLockTest` proves
on MySQL that a second allocation waits at the `SELECT … FOR UPDATE` and then continues the sequence (see
[Running tests](../development/01-running-tests.md)).

## Edge cases

- `next()`, `setNext()` and `peek()` throw a `LogicException` for an owner that isn't saved:
  `App\Models\Client must be saved, before it has sequences.`
- `setNext()` throws an `InvalidArgumentException` for a number below 1:
  `The next number of a sequence must be at least 1, 0 given.`
- `setNext()` may set a number lower than one already allocated, and the sequence then allocates numbers again. Keep a
  unique index on the numbered column (such as `unique(['organization_id', 'number'])`) as a safety net.
- `peek()` neither locks nor creates the row, and answers 1 for a sequence that doesn't exist. Another allocation may
  take the number in the meantime, so use it only for previews.

## Related

- [Base model](00-base-model.md)
- [Current organization](../organizations/01-current-organization.md)
- [Migrations](../database/00-migrations.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
- [Running tests](../development/01-running-tests.md)
