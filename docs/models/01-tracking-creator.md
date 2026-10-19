# Tracking the creator

The `Core\Models\Traits\TracksCreator` trait records the user who created a record in its `created_by_user_id` column.
Use it on models whose author matters, such as invoices or notes, to show or filter by who made them.

## Setup

Add the column to the model's table. It should be a nullable foreign key to `users`, set to `null` when the user is
deleted, so deleting a user never fails on, or deletes, the records they created:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    // ...
    $table->timestamps();
});
```

Then use the trait on the model:

```php
use Core\Models\BaseModel;
use Core\Models\Traits\TracksCreator;

class Invoice extends BaseModel
{
    use TracksCreator;
}
```

## Behavior

- When a record is created without a `created_by_user_id`, the trait sets it to `Auth::id()`: the user that the request
  is signed in as. After switching accounts, that is the account switched to.
- A creator that the record is given is kept, so seeders and imports can set it with `forceFill()`.
- A record created without a signed-in user, such as in a console command, a scheduled task or a queued job, has no
  creator (`null`).
- Updates never change the creator: a record edited by another user keeps its author.
- The `creator()` relation is a `BelongsTo` to `Core\Models\User`.

```php
$invoice = Invoice::query()->create(['number' => 'INV-1', 'total' => 100]);

$invoice->created_by_user_id; // id of the signed-in user
$invoice->creator;            // that User, or null
```

## Deleted creators

Users are soft-deleted, so a deleted user stays the creator, but the `creator` relation, which excludes soft-deleted
users like any query of them, returns `null`. Ask for them explicitly when the author of an old record matters:

```php
$author = $invoice->creator()->withTrashed()->first();
```

When a user is deleted permanently, the recommended column's `nullOnDelete()` clears `created_by_user_id`.

## Filtering and mass assignment

Keep `created_by_user_id` out of the model's `$fillable` and out of the request rules, so a client can't claim another
author. A column that isn't fillable isn't among the default filters and sorts either; list it in the repository's
`$allowedFilters` (and `$allowedSorts`) to let clients filter by the creator, such as
`?filter[created_by_user_id][eq]=5`.

## Related

- [Base model](00-base-model.md)
- [Filters](../querying/00-filters.md)
- [The user model](../users/00-user-model.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Auditing models](../audit/01-auditing-models.md)
