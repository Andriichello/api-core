<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class UniqueEmailTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class UniqueEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user with the email in the organization, or without one.
     *
     * @param string $email
     * @param Organization|null $organization
     *
     * @return User
     */
    private function createUser(string $email, ?Organization $organization = null): User
    {
        return User::factory()->create(['email' => $email, 'organization_id' => $organization?->id]);
    }

    /**
     * Test that an email is unique among the users of an organization, deleted ones included.
     *
     * @return void
     */
    public function testEmailIsUniqueInOrganization(): void
    {
        $acme = Organization::factory()->create();
        $this->createUser('ann@example.com', $acme)->delete();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createUser('ann@example.com', $acme);
    }

    /**
     * Test that an email is unique among the users without an organization too.
     *
     * @return void
     */
    public function testEmailIsUniqueAmongUsersWithoutOrganization(): void
    {
        $this->createUser('ann@example.com');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createUser('ann@example.com');
    }

    /**
     * Test that the same email is fine in different organizations and without one.
     *
     * @return void
     */
    public function testSameEmailIsFineInDifferentOrganizations(): void
    {
        $this->createUser('ann@example.com', Organization::factory()->create());
        $this->createUser('ann@example.com', Organization::factory()->create());
        $this->createUser('ann@example.com');

        $this->assertSame(3, User::query()->where('email', 'ann@example.com')->count());
    }
}
