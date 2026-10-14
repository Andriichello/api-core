<?php

namespace Core\Tests\Unit\Database;

use Core\Database\Factories\OrganizationFactory;
use Core\Database\Factories\UserFactory;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class FactoriesTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Database
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function testModelsResolveTheirFactories(): void
    {
        $this->assertInstanceOf(UserFactory::class, User::factory());
        $this->assertInstanceOf(OrganizationFactory::class, Organization::factory());
    }

    public function testFactoriesCreateTheirModels(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertModelExists($user);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertModelExists($organization);
        $this->assertNotNull($organization->activated_at);
    }

    public function testCreatesUnverifiedUser(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testCreatesUserOfOrganization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->for($organization)->create();

        $this->assertSame($organization->id, $user->organization_id);
    }
}
