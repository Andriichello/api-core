# Async jobs

`Core\Jobs\AsyncJob` is the base of queued jobs that run in an organization: the one current when they were dispatched,
or the one they are dispatched for. Extend it for queued work on organization-scoped records, such as sending reminders
of an organization's overdue invoices.

## Writing a job

`AsyncJob` is abstract and implements `ShouldQueue` with Laravel's `Dispatchable`, `InteractsWithQueue`, `Queueable`
and `SerializesModels`. The job adds its constructor and `handle()`:

```php
namespace App\Jobs;

use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use Core\Jobs\AsyncJob;

class SendOverdueReminders extends AsyncJob
{
    protected bool $requiresOrganization = true;

    public function __construct(
        public int $daysOverdue = 7
    ) {}

    public function handle(): void
    {
        Invoice::query()
            ->where('due_at', '<', now()->subDays($this->daysOverdue))
            ->whereNull('paid_at')
            ->each(fn (Invoice $invoice) => $invoice->client->notify(new InvoiceOverdue($invoice)));
    }
}
```

## The organization of a job

`CurrentOrganization` is kept in Laravel's Context, which Laravel adds to the payload of every queued job and restores
when the job runs. So a job runs in the organization that was current when it was dispatched, and the jobs it
dispatches do too:

| Dispatched | Runs in |
|------------|---------|
| From a request, after the `organization` middleware | The request's organization. |
| Inside `CurrentOrganization::run($organization, ...)` | That organization. |
| With `Job::dispatchFor($organization, ...$arguments)` | That organization, whatever is current. |
| From the scheduler, a console command or code without an organization | None. |

Models with `BelongsToOrganization` see only the records of the current organization, and none without one, so a job
on such records needs an organization. `dispatchFor()` gives it one, and then returns to the organization that was
current before:

```php
use App\Jobs\SendOverdueReminders;
use Core\Models\Organization;
use Illuminate\Support\Facades\Schedule;

// routes/console.php
Schedule::call(function () {
    Organization::query()->each(
        fn (Organization $organization) => SendOverdueReminders::dispatchFor($organization, 14)
    );
})->daily();
```

`dispatchFor()` takes an `Organization` or its id, and the arguments of the job's constructor. It returns nothing.

`CurrentOrganization::run($organization, fn () => SendOverdueReminders::dispatch())` works the same way:
`run()` dispatches a returned pending dispatch inside the organization, rather than after it.

## Requiring an organization

A job with `protected bool $requiresOrganization = true` fails, without running and without retries, when no
organization is current, rather than finding no records of its organization and succeeding quietly. Its `failed()`
method gets a `LogicException`:

```text
App\Jobs\SendOverdueReminders needs an organization, but none is current.
```

The check is a job middleware, so a job that overrides `middleware()` keeps it by merging the parent's:

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public function middleware(): array
{
    return [...parent::middleware(), new WithoutOverlapping('overdue-reminders')];
}
```

`dispatchSync()` runs the job through the `sync` queue connection, middleware included, so it is checked too.

## Retries

A job is tried 3 times (`$tries`). `backoff()` returns `[1, 5]`: the first retry waits 1 second and the second one
5 seconds. Override either in the job, with one wait for each try after the first:

```php
public int $tries = 4;

public function backoff(): array
{
    return [10, 60, 300];
}
```

## Related

- [Sync jobs](01-sync-jobs.md)
- [Current organization](../organizations/01-current-organization.md)
- [Resolving the organization](../organizations/02-resolving-the-organization.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Scheduled tasks](../getting-started/05-scheduled-tasks.md)
