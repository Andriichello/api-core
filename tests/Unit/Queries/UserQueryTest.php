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

    /**
     * Create an organization with a unique slug.
     *
     * @return Organization
     */
    private function createOrganization(): Organization
    {
        return Organization::create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);
    }

    /**
     * Create a user in the organization, which is linked to the main account, if they are given.
     *
     * @param Organization|null $organization
     * @param User|null $mainAccount
     *
     * @return User
     */
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

    /**
     * Create a super admin in the organization, if it is given.
     *
     * @param Organization|null $organization
     *
     * @return User
     */
    private function createSuperAdmin(?Organization $organization = null): User
    {
        Role::create(['name' => UserRole::SuperAdmin->value]);

        $user = $this->createUser($organization);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    /**
     * Test that the index query returns nothing without a user.
     *
     * @return void
     */
    public function testReturnsNothingWithoutUser(): void
    {
        $this->createUser();
        $this->createUser($this->createOrganization());

        $users = User::query()->index(null)->get();

        $this->assertCount(0, $users);
    }

    /**
     * Test that the index query returns the user and the members of the user's organization.
     *
     * @return void
     */
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

    /**
     * Test that the index query returns only the user, when the user has no organization.
     *
     * @return void
     */
    public function testReturnsOnlyItselfForUserWithoutOrganization(): void
    {
        $user = $this->createUser();

        $this->createUser();
        $this->createUser($this->createOrganization());

        $ids = User::query()->index($user)->pluck('id')->all();

        $this->assertEquals([$user->id], $ids);
    }

    /**
     * Test that the index query returns all users for a super admin without an organization.
     *
     * @return void
     */
    public function testReturnsAllUsersForSuperAdminWithoutOrganization(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createUser();
        $this->createUser($this->createOrganization());

        $users = User::query()->index($superAdmin)->get();

        $this->assertCount(3, $users);
    }

    /**
     * Test that the index query scopes a super admin with an organization to that organization.
     *
     * @return void
     */
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

    /**
     * Test that the index query returns the linked accounts of a main account.
     *
     * @return void
     */
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

    /**
     * Test that the index query returns the main and sibling accounts of a linked account.
     *
     * @return void
     */
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

    /**
     * Test that the index query returns only the own accounts of a user without an organization.
     *
     * @return void
     */
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
