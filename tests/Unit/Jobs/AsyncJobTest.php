<?php

namespace Core\Tests\Unit\Jobs;

use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class AsyncJobTest.
 *
 * Jobs on the `sync` queue, which serializes them, with the Context, as any queue does.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Jobs
 */
class AsyncJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run the jobs on the `sync` queue, and forget what they recorded.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);
        RecordingJob::forget();
    }

    /**
     * Test that a job runs in the organization, in which it was dispatched, though the dispatch was returned from
     * it, or in the one it was dispatched for, which is then no longer current.
     *
     * @return void
     */
    public function testRunsInItsOrganization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        CurrentOrganization::run($acme, fn () => RecordingJob::dispatch());
        RecordingJob::dispatchFor($globex);
        RecordingJob::dispatch();

        $this->assertSame([$acme->id, $globex->id, null], RecordingJob::$organizations);
        $this->assertNull(CurrentOrganization::id());
    }

    /**
     * Test that a job, which requires an organization, fails without one, rather than running.
     *
     * @return void
     */
    public function testJobRequiringOrganizationFailsWithoutOne(): void
    {
        RecordingJob::dispatchFor(Organization::factory()->create(), true);
        RecordingJob::dispatch(true);
        RecordingJob::dispatchSync(true);

        $this->assertCount(1, RecordingJob::$organizations);
        $this->assertSame(
            array_fill(0, 2, RecordingJob::class . ' needs an organization, but none is current.'),
            RecordingJob::$failures
        );
    }

    /**
     * Test that a job is retried with an increasing wait.
     *
     * @return void
     */
    public function testRetriesWithIncreasingWait(): void
    {
        $job = new RecordingJob;

        $this->assertSame(3, $job->tries);
        $this->assertSame([1, 5, 10], $job->backoff());
        $this->assertSame([], $job->middleware());
    }
}
