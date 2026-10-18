<?php

namespace Core\Tests\Unit\Jobs;

use Core\Helpers\CurrentOrganization;
use Core\Jobs\AsyncJob;
use Throwable;

/**
 * Class RecordingJob.
 *
 * A queued job for AsyncJobTest, which records the organization, in which it
 * runs, or the failure, which stops it. It is a named class, since a queue
 * can't serialize an anonymous one.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Jobs
 */
class RecordingJob extends AsyncJob
{
    /**
     * Organizations, in which the jobs ran.
     *
     * @var array<int, int|null>
     */
    public static array $organizations = [];

    /**
     * Messages of the failures, which stopped the jobs.
     *
     * @var array<int, string>
     */
    public static array $failures = [];

    /**
     * RecordingJob constructor.
     *
     * @param bool $requiresOrganization
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function __construct(bool $requiresOrganization = false)
    {
        $this->requiresOrganization = $requiresOrganization;
    }

    /**
     * Forget what the jobs recorded.
     *
     * @return void
     */
    public static function forget(): void
    {
        static::$organizations = [];
        static::$failures = [];
    }

    /**
     * Execute the job: record the organization, in which it runs.
     *
     * @return void
     */
    public function handle(): void
    {
        static::$organizations[] = CurrentOrganization::id();
    }

    /**
     * Handle a job failure: record its message.
     *
     * @param Throwable|null $exception
     *
     * @return void
     */
    public function failed(?Throwable $exception): void
    {
        static::$failures[] = (string) $exception?->getMessage();
    }
}
