<?php

namespace Core\Jobs;

use Closure;
use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LogicException;

/**
 * Class AsyncJob.
 *
 * A queued job, which runs in the organization, in which it was dispatched,
 * as CurrentOrganization travels in the Context. `dispatchFor()` dispatches
 * it in the given organization, such as from the scheduler, which acts in
 * none. A job, which `$requiresOrganization`, fails without one, rather than
 * finding no records of its organization. It is tried 3 times, waiting 1, 5
 * and 10 seconds in between.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Jobs
 */
abstract class AsyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Determines if the job fails, unless an organization is current, when it
     * runs.
     *
     * @var bool
     */
    protected bool $requiresOrganization = false;

    /**
     * Dispatch the job, made of the given arguments, in the given
     * organization.
     *
     * @param Organization|int $organization
     * @param mixed ...$arguments
     *
     * @return void
     */
    public static function dispatchFor(Organization|int $organization, mixed ...$arguments): void
    {
        CurrentOrganization::run($organization, fn () => static::dispatch(...$arguments));
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, callable>
     */
    public function middleware(): array
    {
        return $this->requiresOrganization ? [$this->ensureOrganization(...)] : [];
    }

    /**
     * Fail the job, unless an organization is current.
     *
     * @param AsyncJob $job
     * @param Closure $next
     *
     * @return mixed
     */
    protected function ensureOrganization(AsyncJob $job, Closure $next): mixed
    {
        if (CurrentOrganization::isSet()) {
            return $next($job);
        }

        $this->fail(new LogicException(static::class . ' needs an organization, but none is current.'));

        return null;
    }
}
