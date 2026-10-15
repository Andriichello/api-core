<?php

namespace Core\Tests\Unit\Queries;

use Core\Enums\UserRole;
use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class OrganizationQueryTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Queries
 */
class OrganizationQueryTest extends TestCase
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
     * Create a user in the organization, if it is given.
     *
     * @param Organization|null $organization
     *
     * @return User
     */
    private function createUser(?Organization $organization = null): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
    }

    /**
     * Test that the index query returns nothing without a user.
     *
     * @return void
     */
    public function testReturnsNothingWithoutUser(): void
    {
        $this->createOrganization();

        $organizations = Organization::query()->index(null)->get();

        $this->assertCount(0, $organizations);
    }

    /**
     * Test that the index query returns only the organization of the user.
     *
     * @return void
     */
    public function testReturnsOnlyOrganizationOfTheUser(): void
    {
        $organization = $this->createOrganization();
        $this->createOrganization();

        $user = $this->createUser($organization);

        $ids = Organization::query()->index($user)->pluck('id')->all();

        $this->assertEquals([$organization->id], $ids);
    }

    /**
     * Test that the index query returns nothing for a user without an organization.
     *
     * @return void
     */
    public function testReturnsNothingForUserWithoutOrganization(): void
    {
        $this->createOrganization();

        $user = $this->createUser();

        $organizations = Organization::query()->index($user)->get();

        $this->assertCount(0, $organizations);
    }

    /**
     * Test that the index query returns all organizations for a super admin without an organization.
     *
     * @return void
     */
    public function testReturnsAllOrganizationsForSuperAdminWithoutOrganization(): void
    {
        $this->createOrganization();
        $this->createOrganization();

        Role::create(['name' => UserRole::SuperAdmin->value]);

        $superAdmin = $this->createUser();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $organizations = Organization::query()->index($superAdmin)->get();

        $this->assertCount(2, $organizations);
    }

    /**
     * Test that a super admin, who acts in an organization, gets only that organization.
     *
     * @return void
     */
    public function testReturnsOnlyTheOrganizationInWhichSuperAdminActs(): void
    {
        $organization = $this->createOrganization();
        $this->createOrganization();

        Role::create(['name' => UserRole::SuperAdmin->value]);

        $superAdmin = $this->createUser();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $ids = CurrentOrganization::run(
            $organization,
            fn () => Organization::query()->index($superAdmin)->pluck('id')->all()
        );

        $this->assertEquals([$organization->id], $ids);
    }

    /**
     * Test that a current organization, which isn't the user's one, doesn't change what a user, who isn't a super
     * admin, gets.
     *
     * @return void
     */
    public function testCurrentOrganizationDoesNotWidenOtherUsers(): void
    {
        $organization = $this->createOrganization();
        $other = $this->createOrganization();

        $user = $this->createUser($organization);

        $ids = CurrentOrganization::run($other, fn () => Organization::query()->index($user)->pluck('id')->all());

        $this->assertEquals([$organization->id], $ids);
    }
}
