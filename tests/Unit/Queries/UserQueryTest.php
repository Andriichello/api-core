<?php

namespace Core\Tests\Unit\Queries;

use Core\Enums\UserRole;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class UserQueryTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Queries
 */
class UserQueryTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(): Organization
    {
        return Organization::create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);
    }

    private function createUser(?Organization $organization = null, ?User $mainAccount = null): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'main_account_id' => $mainAccount?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
    }

    private function createSuperAdmin(?Organization $organization = null): User
    {
        Role::create(['name' => UserRole::SuperAdmin->value]);

        $user = $this->createUser($organization);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function testReturnsNothingWithoutUser(): void
    {
        $this->createUser();
        $this->createUser($this->createOrganization());

        $users = User::query()->index(null)->get();

        $this->assertCount(0, $users);
    }

    public function testReturnsUserAndMembersOfTheSameOrganization(): void
    {
        $organization = $this->createOrganization();

        $user = $this->createUser($organization);
        $colleague = $this->createUser($organization);

        $this->createUser($this->createOrganization());
        $this->createUser();

        $ids = User::query()->index($user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$user->id, $colleague->id], $ids);
    }

    public function testReturnsOnlyItselfForUserWithoutOrganization(): void
    {
        $user = $this->createUser();

        $this->createUser();
        $this->createUser($this->createOrganization());

        $ids = User::query()->index($user)->pluck('id')->all();

        $this->assertEquals([$user->id], $ids);
    }

    public function testReturnsAllUsersForSuperAdminWithoutOrganization(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createUser();
        $this->createUser($this->createOrganization());

        $users = User::query()->index($superAdmin)->get();

        $this->assertCount(3, $users);
    }

    public function testScopesSuperAdminWithOrganizationToThatOrganization(): void
    {
        $organization = $this->createOrganization();

        $superAdmin = $this->createSuperAdmin($organization);
        $colleague = $this->createUser($organization);

        $this->createUser($this->createOrganization());
        $this->createUser();

        $ids = User::query()->index($superAdmin)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$superAdmin->id, $colleague->id], $ids);
    }

    public function testReturnsLinkedAccountsForMainAccount(): void
    {
        $organization = $this->createOrganization();

        $main = $this->createUser($organization);
        $colleague = $this->createUser($organization);
        $linked = $this->createUser($this->createOrganization(), $main);

        $this->createUser($this->createOrganization());
        $this->createUser();

        $ids = User::query()->index($main)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$main->id, $colleague->id, $linked->id], $ids);
    }

    public function testReturnsMainAndSiblingAccountsForLinkedAccount(): void
    {
        $organization = $this->createOrganization();

        $main = $this->createUser($this->createOrganization());
        $linked = $this->createUser($organization, $main);
        $sibling = $this->createUser($this->createOrganization(), $main);
        $colleague = $this->createUser($organization);

        $stranger = $this->createUser($this->createOrganization());
        $this->createUser($this->createOrganization(), $stranger);
        $this->createUser();

        $ids = User::query()->index($linked)->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$main->id, $linked->id, $sibling->id, $colleague->id],
            $ids
        );
    }

    public function testReturnsOnlyOwnAccountsForUserWithoutOrganization(): void
    {
        $main = $this->createUser();
        $linked = $this->createUser($this->createOrganization(), $main);

        $this->createUser();
        $this->createUser($this->createOrganization());

        $ids = User::query()->index($main)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$main->id, $linked->id], $ids);
    }
}
