<?php

namespace Core\Jobs;

use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Class SyncJob.
 *
 * A job, which runs right away, in the process, which dispatches it, and so
 * in its organization. `dispatchFor()` runs it in the given organization.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Jobs
 */
abstract class SyncJob
{
    use Dispatchable;

    /**
     * Run the job, made of the given arguments, in the given organization.
     *
     * @param Organization|int $organization
     * @param mixed ...$arguments
     *
     * @return mixed The job's result.
     */
    public static function dispatchFor(Organization|int $organization, mixed ...$arguments): mixed
    {
        return CurrentOrganization::run($organization, fn () => static::dispatchSync(...$arguments));
    }
}
