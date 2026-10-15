<?php

namespace Core\Tests\Unit\Database;

use Core\Database\Seeders\RolesAndPermissionsSeeder;
use Core\Enums\UserRole;
use Core\Helpers\Helpers;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class RolesAndPermissionsSeederTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Database
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get the names of the permissions for the actions on the resource.
     *
     * @param string $resource
     * @param string ...$actions
     *
     * @return array<int, string>
     */
    private function permissions(string $resource, string ...$actions): array
    {
        return array_map(fn (string $action) => $action . ' ' . Helpers::morphOf($resource), $actions);
    }

    /**
     * Get the names of the role's permissions.
     *
     * @param string $role
     *
     * @return array<int, string>
     */
    private function permissionsOf(string $role): array
    {
        return Role::findByName($role)->permissions->pluck('name')->all();
    }

    /**
     * Test that the seeder creates every user role.
     *
     * @return void
     */
    public function testSeedsRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            UserRole::values(),
            Role::query()->pluck('name')->all()
        );
    }

    /**
     * Test that the default roles get their permissions: an admin manages the users and updates the
     * organization, but doesn't create or delete organizations, a user only views them, and the super admin
     * needs none.
     *
     * @return void
     */
    public function testGivesDefaultRolesTheirPermissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            [
                ...$this->permissions(User::class, 'viewAny', 'view', 'create', 'update', 'delete'),
                ...$this->permissions(User::class, 'attachRole', 'attachAnyRole', 'detachRole'),
                ...$this->permissions(Organization::class, 'viewAny', 'view', 'update'),
                ...$this->permissions(Role::class, 'attachUser', 'attachAnyUser', 'detachUser'),
            ],
            $this->permissionsOf(UserRole::Admin->value)
        );

        $this->assertEqualsCanonicalizing(
            [
                ...$this->permissions(User::class, 'viewAny', 'view'),
                ...$this->permissions(Organization::class, 'viewAny', 'view'),
            ],
            $this->permissionsOf(UserRole::User->value)
        );

        $this->assertSame([], $this->permissionsOf(UserRole::SuperAdmin->value));
    }

    /**
     * Test that the roles, which the application defines, replace the default ones, while the super admin is
     * always there.
     *
     * @return void
     */
    public function testSeedsRolesOfTheApplication(): void
    {
        config(['api-core.roles' => [
            ['name' => 'owner', 'permissions' => [Organization::class => 'all']],
            ['name' => 'accountant', 'permissions' => [Organization::class => ['viewAny', 'view', 'update']]],
            ['name' => 'viewer', 'permissions' => [Organization::class => ['viewAny', 'view']]],
        ]]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            [UserRole::SuperAdmin->value, 'owner', 'accountant', 'viewer'],
            Role::query()->pluck('name')->all()
        );
        $this->assertEqualsCanonicalizing(
            $this->permissions(Organization::class, 'viewAny', 'view', 'update'),
            $this->permissionsOf('accountant')
        );
    }

    /**
     * Test that the seeder runs again, giving the roles exactly the permissions, which are configured now, and
     * keeping the roles of users, whose permissions change right away.
     *
     * @return void
     */
    public function testSyncsAgainKeepingRolesOfUsers(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(UserRole::User->value);

        $this->assertTrue($user->can('view ' . Helpers::morphOf(Organization::class)));

        config(['api-core.roles' => [
            ['name' => 'user', 'permissions' => [User::class => ['viewAny', 'view', 'create']]],
        ]]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $user = $user->fresh();

        $this->assertTrue($user->isOfRole(UserRole::User));
        $this->assertEqualsCanonicalizing(
            $this->permissions(User::class, 'viewAny', 'view', 'create'),
            $this->permissionsOf(UserRole::User->value)
        );
        $this->assertTrue($user->can('create ' . Helpers::morphOf(User::class)));
        $this->assertFalse($user->can('view ' . Helpers::morphOf(Organization::class)));
    }
}
