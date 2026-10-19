<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class OrganizationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that soft-deleting an organization soft-deletes its users at the same time, while the users, which
     * were deleted before, keep their time, and the users of other organizations stay.
     *
     * @return void
     */
    public function testSoftDeletingDeletesItsUsers(): void
    {
        $acme = Organization::factory()->create();
        $users = User::factory()->count(2)->create(['organization_id' => $acme->id]);
        $earlier = User::factory()->create(['organization_id' => $acme->id]);
        $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $earlier->delete();
        $this->travel(1)->hour();

        $acme->delete();

        foreach ($users as $user) {
            $this->assertSoftDeleted($user);
            $this->assertEquals($acme->deleted_at, $user->fresh()->deleted_at);
        }

        $this->assertEquals($earlier->deleted_at, $earlier->fresh()->deleted_at);
        $this->assertNotSoftDeleted($other);
    }

    /**
     * Test that restoring an organization restores the users, which were deleted with it, but not the ones,
     * which were deleted before it.
     *
     * @return void
     */
    public function testRestoringRestoresUsersDeletedWithIt(): void
    {
        $acme = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $acme->id]);
        $earlier = User::factory()->create(['organization_id' => $acme->id]);

        $earlier->delete();
        $this->travel(1)->hour();

        $acme->delete();
        $this->assertSoftDeleted($user);
        $this->travel(1)->hour();

        $acme->fresh()->restore();

        $this->assertNotSoftDeleted($user);
        $this->assertSoftDeleted($earlier);
    }

    /**
     * Test that deleting an organization permanently deletes its users, deleted ones included, through their
     * models, so the accounts linked to one of them in other organizations stay linked to each other.
     *
     * @return void
     */
    public function testForceDeletingHandsLinkedAccountsOver(): void
    {
        $acme = Organization::factory()->create();
        $main = User::factory()->create(['organization_id' => $acme->id, 'email' => 'ann@example.com']);
        $deleted = User::factory()->create(['organization_id' => $acme->id]);
        $deleted->delete();

        $linked = fn () => User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'email' => 'ann@example.com',
            'main_account_id' => $main->id,
        ]);
        $beta = $linked();
        $gamma = $linked();

        $acme->forceDelete();

        $this->assertModelMissing($main);
        $this->assertModelMissing($deleted);
        $this->assertNull($beta->fresh()?->main_account_id);
        $this->assertSame($beta->id, $gamma->fresh()?->main_account_id);
    }
}
