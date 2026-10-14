<?php

namespace Core\Tests\Unit\Queries;

use Core\Enums\UserRole;
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

    private function createOrganization(): Organization
    {
        return Organization::create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);
    }

    private function createUser(?Organization $organization = null): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
    }

    public function testReturnsNothingWithoutUser(): void
    {
        $this->createOrganization();

        $organizations = Organization::query()->index(null)->get();

        $this->assertCount(0, $organizations);
    }

    public function testReturnsOnlyOrganizationOfTheUser(): void
    {
        $organization = $this->createOrganization();
        $this->createOrganization();

        $user = $this->createUser($organization);

        $ids = Organization::query()->index($user)->pluck('id')->all();

        $this->assertEquals([$organization->id], $ids);
    }

    public function testReturnsNothingForUserWithoutOrganization(): void
    {
        $this->createOrganization();

        $user = $this->createUser();

        $organizations = Organization::query()->index($user)->get();

        $this->assertCount(0, $organizations);
    }

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
}
