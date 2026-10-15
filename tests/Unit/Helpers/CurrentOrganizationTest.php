<?php

namespace Core\Tests\Unit\Helpers;

use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Context\Repository;
use RuntimeException;

/**
 * Class CurrentOrganizationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Helpers
 */
class CurrentOrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the organization is set by the model or by its id, and unset with null.
     *
     * @return void
     */
    public function testSetsAndUnsetsTheOrganization(): void
    {
        $organization = Organization::factory()->create();

        $this->assertFalse(CurrentOrganization::isSet());
        $this->assertNull(CurrentOrganization::id());
        $this->assertNull(CurrentOrganization::get());

        CurrentOrganization::set($organization);

        $this->assertTrue(CurrentOrganization::isSet());
        $this->assertSame($organization->id, CurrentOrganization::id());
        $this->assertTrue($organization->is(CurrentOrganization::get()));

        CurrentOrganization::set(42);
        $this->assertSame(42, CurrentOrganization::id());

        CurrentOrganization::set(null);
        $this->assertFalse(CurrentOrganization::isSet());
    }

    /**
     * Test that `run()` acts in the organization only within the callback, returns its result, and restores the
     * previous organization, even when the callback throws.
     *
     * @return void
     */
    public function testRunRestoresThePreviousOrganization(): void
    {
        CurrentOrganization::set(1);

        $this->assertSame(2, CurrentOrganization::run(2, fn () => CurrentOrganization::id()));
        $this->assertNull(CurrentOrganization::run(null, fn () => CurrentOrganization::id()));
        $this->assertSame(1, CurrentOrganization::id());

        try {
            CurrentOrganization::run(3, fn () => throw new RuntimeException('Failed'));
        } catch (RuntimeException) {
            // The organization is restored all the same
        }

        $this->assertSame(1, CurrentOrganization::id());
    }

    /**
     * Test that the organization is kept in the Context, so it travels with queued jobs, which dehydrate the
     * Context, and hydrate it, when they run.
     *
     * @return void
     */
    public function testTravelsWithTheContext(): void
    {
        CurrentOrganization::set(5);

        $context = app(Repository::class);
        $dehydrated = $context->dehydrate();

        $context->flush();
        $this->assertNull(CurrentOrganization::id());

        $context->hydrate($dehydrated);
        $this->assertSame(5, CurrentOrganization::id());
    }
}
