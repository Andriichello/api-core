<?php

namespace Core\Tests\Unit\Jobs;

use Core\Helpers\CurrentOrganization;
use Core\Jobs\SyncJob;
use Core\Tests\TestCase;

/**
 * Class SyncJobTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Jobs
 */
class SyncJobTest extends TestCase
{
    /**
     * Test that a job runs right away in the organization, it was dispatched for, and gives its result, while the
     * organization is no longer current after it.
     *
     * @return void
     */
    public function testRunsInTheOrganizationItIsDispatchedFor(): void
    {
        $job = new class('rates') extends SyncJob
        {
            public function __construct(
                private readonly string $name
            ) {}

            public function handle(): string
            {
                return $this->name . ' of ' . CurrentOrganization::id();
            }
        };

        $this->assertSame('rates of 7', $job::dispatchFor(7, 'rates'));
        $this->assertNull(CurrentOrganization::id());
    }
}
