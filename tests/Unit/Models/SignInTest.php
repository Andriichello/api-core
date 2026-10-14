<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class SignInTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class SignInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that active users, with or without an organization, can sign in.
     *
     * @return void
     */
    public function testActiveUsersCanSignIn(): void
    {
        $this->assertTrue(User::factory()->create()->canSignIn());
        $this->assertTrue(User::factory()->for(Organization::factory())->create()->canSignIn());
    }

    /**
     * Test that suspended users cannot sign in.
     *
     * @return void
     */
    public function testSuspendedUsersCannotSignIn(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);

        $this->assertTrue($user->isSuspended());
        $this->assertFalse($user->canSignIn());
    }

    /**
     * Test that users of suspended, deactivated or deleted organizations cannot sign in.
     *
     * @return void
     */
    public function testUsersOfInactiveOrganizationsCannotSignIn(): void
    {
        $suspended = Organization::factory()->suspended()->create();
        $deactivated = Organization::factory()->deactivated()->create();
        $deleted = Organization::factory()->create();

        $users = [
            User::factory()->for($suspended)->create(),
            User::factory()->for($deactivated)->create(),
            User::factory()->for($deleted)->create(),
        ];

        $deleted->delete();

        $this->assertTrue($suspended->isSuspended());
        $this->assertTrue($deactivated->isDeactivated());

        foreach ($users as $user) {
            $this->assertFalse($user->fresh()->canSignIn());
        }
    }
}
