# Current organization

`Core\Helpers\CurrentOrganization` holds the organization in which the current request, job or command acts, and
whose records the [organization-scoped models](03-organization-scoped-models.md) see. Use it to read the organization
in your code, and to act in one where no request sets it.

## Who sets it

- For a request, the `organization` middleware sets it from the signed-in user, or from the organization a super
  admin names (see [Resolving the organization](02-resolving-the-organization.md)).
- A queued job acts in the organization in which it was dispatched, since the id travels with Laravel's Context.
- Console commands, the scheduler, seeders and tests act in none, until they set it with `run()` or `set()`.

## Methods

| Method                                         | Description                                                       |
|------------------------------------------------|-------------------------------------------------------------------|
| `id(): ?int`                                   | Id of the current organization, or `null` when it isn't set.      |
| `get(): ?Organization`                         | The current organization, looked up by its id, or `null`.         |
| `isSet(): bool`                                | Whether an organization is current.                               |
| `set(Organization\|int\|null $organization)`   | Makes the organization (a model or an id) current, or unsets it with `null`. |
| `run(Organization\|int\|null $organization, Closure $callback)` | Runs the callback in the organization and returns its result. |

`get()` queries the database on every call, so prefer `id()` when the id is enough. `set()` doesn't check that the
organization exists.

## Running code in an organization

`run()` makes the organization current for the callback only, and then restores the one that was current before,
even when the callback throws:

```php
use Core\Helpers\CurrentOrganization;
use App\Models\Invoice;

$total = CurrentOrganization::run($organization, fn () => Invoice::query()->sum('amount'));

// Acting in no organization: the scoped models find nothing here
CurrentOrganization::run(null, fn () => Invoice::query()->count()); // 0
```

A console command, which goes through every organization:

```php
use Core\Models\Organization;

Organization::query()->each(function (Organization $organization) {
    CurrentOrganization::run($organization, fn () => $this->closeOverdueInvoices());
});
```

## Laravel Context and queued jobs

The id is kept in Laravel's `Context` under `CurrentOrganization::CONTEXT_KEY` (`api-core.organization_id`). Laravel
dehydrates the Context into every queued job and hydrates it when the job runs, so a job dispatched during a request
runs in the request's organization, with no code of its own. Context data is also added to the log entries'
metadata, so the logs name the organization.

A job dispatched where no organization is current runs in none. Dispatch it inside `run()`:

```php
use App\Jobs\SendInvoiceReminders;

CurrentOrganization::run($organization, fn () => SendInvoiceReminders::dispatch());
```

The callback returns a `PendingDispatch`, which would dispatch the job only when it is destroyed, after the
organization was restored. `run()` destroys it inside the organization instead, and returns `null`. The base jobs do
this for you: `AsyncJob::dispatchFor($organization, ...$arguments)` and `SyncJob::dispatchFor()`, and an `AsyncJob`
with `$requiresOrganization` fails, rather than running in no organization (see [Async jobs](../jobs/00-async-jobs.md)).

## Tests

Create the records of an organization inside `run()`, as api-core's own tests do. `set()` would leave the
organization current for the rest of the test, so later queries would be scoped by it too:

```php
$invoice = CurrentOrganization::run($acme, fn () => Invoice::query()->create(['number' => 'INV-1']));
```

## Related

- [Organizations](00-overview.md)
- [Resolving the organization](02-resolving-the-organization.md)
- [Organization-scoped models](03-organization-scoped-models.md)
- [Acting organization](04-acting-organization.md)
- [Async jobs](../jobs/00-async-jobs.md)
- [Sync jobs](../jobs/01-sync-jobs.md)
