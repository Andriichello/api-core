# Sync jobs

`Core\Jobs\SyncJob` is the base of jobs that run right away, in the process that dispatches them, and give back their
result. Extend it for work that must run in a given organization from code that has none, such as a console command
computing an organization's totals, or that should stay a job class without being queued.

## Writing a job

`SyncJob` is abstract and uses Laravel's `Dispatchable`; it isn't queued. The job adds its constructor and a `handle()`
that returns its result:

```php
namespace App\Jobs;

use App\Models\Invoice;
use Core\Jobs\SyncJob;

class TotalOfUnpaidInvoices extends SyncJob
{
    public function __construct(
        public string $currency
    ) {}

    public function handle(): int
    {
        return (int) Invoice::query()
            ->where('currency', $this->currency)
            ->whereNull('paid_at')
            ->sum('total');
    }
}
```

## Running it

| Call | Runs in | Returns |
|------|---------|---------|
| `Job::dispatchFor($organization, ...$arguments)` | The given organization (an `Organization` or its id). | The result of `handle()`. |
| `Job::dispatchSync(...$arguments)` | The current organization. | The result of `handle()`. |
| `Job::dispatch(...$arguments)` | The current organization. | Nothing: the job runs, but its result is dropped. |

```php
use App\Jobs\TotalOfUnpaidInvoices;

$total = TotalOfUnpaidInvoices::dispatchFor($organization, 'EUR');
```

`dispatchFor()` sets the organization only while the job runs, and then returns to the one that was current before,
even when the job throws. It doesn't check that the organization exists: an id is used as it is given.

Inside a request, after the `organization` middleware, `dispatchSync()` runs the job in the request's organization, so
`dispatchFor()` is needed only to run it in another one, or where none is current (console commands, the scheduler,
tests).

## Compared with AsyncJob

| | `SyncJob` | `AsyncJob` |
|-|-----------|------------|
| Runs | Right away, in the same process | On a queue worker |
| Organization | The current one, or the one of `dispatchFor()` | The one current at dispatch, or the one of `dispatchFor()` |
| `dispatchFor()` returns | The job's result | Nothing |
| Retries, `$requiresOrganization` | No | Yes |

A job that should fail without an organization checks `CurrentOrganization::isSet()` in its `handle()`, since
`SyncJob` has no `$requiresOrganization`.

## Related

- [Async jobs](00-async-jobs.md)
- [Current organization](../organizations/01-current-organization.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
