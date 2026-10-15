<?php

namespace Core\Tests\Unit\Models;

use Core\Enums\UserRole;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class RoleRankingTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class RoleRankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Define the roles of an application, from the highest to the lowest.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['api-core.roles' => [
            ['name' => 'owner'],
            ['name' => 'accountant'],
            ['name' => 'viewer'],
        ]]);
    }

    /**
     * Create a user with the roles.
     *
     * @param string ...$roles
     *
     * @return User
     */
    private function createUser(string ...$roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            $user->assignRole(Role::findOrCreate($role));
        }

        return $user;
    }

    /**
     * Test that the roles rank in the configured order, below the super admin.
     *
     * @return void
     */
    public function testRanksRolesInTheConfiguredOrder(): void
    {
        $superAdmin = $this->createUser(UserRole::SuperAdmin->value);
        $owner = $this->createUser('owner');
        $accountant = $this->createUser('accountant');
        $viewer = $this->createUser('viewer');

        /** @var Role $viewerRole */
        $viewerRole = Role::findByName('viewer');

        $this->assertTrue($superAdmin->isHigherThanRole('owner'));
        $this->assertFalse($superAdmin->isHigherThanRole(UserRole::SuperAdmin));

        $this->assertTrue($owner->isHigherThanRole('accountant'));
        $this->assertTrue($owner->isHigherThanRole($viewerRole));
        $this->assertFalse($owner->isHigherThanRole('owner'));
        $this->assertFalse($owner->isHigherThanRole(UserRole::SuperAdmin));

        $this->assertTrue($accountant->isHigherThanRole('viewer'));
        $this->assertFalse($accountant->isHigherThanRole('owner'));
        $this->assertFalse($viewer->isHigherThanRole('viewer'));

        $this->assertTrue($owner->isHigherThan($accountant));
        $this->assertFalse($accountant->isHigherThan($owner));
        $this->assertTrue($superAdmin->isHigherThan($owner));
    }

    /**
     * Test that a role, which isn't configured, doesn't throw and is only below the super admin, and a user, who
     * has only such roles, is higher than no role.
     *
     * @return void
     */
    public function testRoleWhichIsNotConfiguredIsOnlyBelowSuperAdmin(): void
    {
        $superAdmin = $this->createUser(UserRole::SuperAdmin->value);
        $owner = $this->createUser('owner');
        $custom = $this->createUser('custom');

        $this->assertTrue($superAdmin->isHigherThanRole('custom'));
        $this->assertFalse($owner->isHigherThanRole('custom'));
        $this->assertFalse($owner->isHigherThanRole(UserRole::Admin));
        $this->assertFalse($custom->isHigherThanRole('viewer'));
        $this->assertNull($custom->rankOfHighestRole());
    }

    /**
     * Test that the user's highest role counts, when the user has several.
     *
     * @return void
     */
    public function testHighestOfSeveralRolesCounts(): void
    {
        $user = $this->createUser('viewer', 'accountant');

        $this->assertSame(2, $user->rankOfHighestRole());
        $this->assertTrue($user->isHigherThanRole('viewer'));
        $this->assertFalse($user->isHigherThanRole('accountant'));
    }

    /**
     * Test that the available roles are the user's own role and the lower ones, from the highest to the lowest.
     *
     * @return void
     */
    public function testAvailableRolesAreTheOwnAndLowerOnes(): void
    {
        $this->assertSame(['accountant', 'viewer'], $this->createUser('accountant')->availableRoles());
        $this->assertSame([], $this->createUser()->availableRoles());
        $this->assertSame(
            [UserRole::SuperAdmin->value, 'owner', 'accountant', 'viewer'],
            $this->createUser(UserRole::SuperAdmin->value)->availableRoles()
        );
    }
}
