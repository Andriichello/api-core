<?php

namespace Core\Tests\Unit\Models;

use Core\Enums\UserRole;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class MainAccountTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class MainAccountTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(): Organization
    {
        return Organization::query()->create([
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

    public function testLinkedAccountBelongsToMainAccount(): void
    {
        $main = $this->createUser($this->createOrganization());
        $linked = $this->createUser($this->createOrganization(), $main);
        $other = $this->createUser($this->createOrganization(), $main);

        $this->assertTrue($linked->mainAccount->is($main));
        $this->assertNull($main->mainAccount);
        $this->assertEqualsCanonicalizing(
            [$linked->id, $other->id],
            $main->relatedAccounts->pluck('id')->all()
        );
        $this->assertCount(0, $linked->relatedAccounts);
    }

    public function testDeterminesMainAccount(): void
    {
        $main = $this->createUser();
        $linked = $this->createUser(mainAccount: $main);

        $this->assertTrue($main->isMainAccount());
        $this->assertFalse($linked->isMainAccount());

        $this->assertSame($main->id, $main->mainAccountId());
        $this->assertSame($main->id, $linked->mainAccountId());
        $this->assertSame($main->id, $linked->fresh()->mainAccountId());
    }

    public function testDeterminesSamePerson(): void
    {
        $main = $this->createUser($this->createOrganization());
        $linked = $this->createUser($this->createOrganization(), $main);
        $sibling = $this->createUser($this->createOrganization(), $main);

        $stranger = $this->createUser($this->createOrganization());
        $strangerLinked = $this->createUser($this->createOrganization(), $stranger);

        $this->assertTrue($main->isSamePerson($main));
        $this->assertTrue($main->isSamePerson($linked));
        $this->assertTrue($linked->isSamePerson($main));
        $this->assertTrue($linked->isSamePerson($sibling));
        $this->assertTrue($linked->isSamePerson($linked));

        $this->assertFalse($main->isSamePerson($stranger));
        $this->assertFalse($linked->isSamePerson($stranger));
        $this->assertFalse($linked->isSamePerson($strangerLinked));
        $this->assertFalse($stranger->isSamePerson($linked));
    }

    public function testMainAccountIsNotMassAssignable(): void
    {
        $main = $this->createUser();

        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'main_account_id' => $main->id,
        ]);

        $this->assertNull($user->main_account_id);
    }

    public function testMainAccountIsHiddenFromSerialization(): void
    {
        $linked = $this->createUser(mainAccount: $this->createUser());

        $this->assertArrayNotHasKey('main_account_id', $linked->toArray());
    }

    public function testLinkedAccountsBecomeMainWhenMainAccountIsDeletedPermanently(): void
    {
        $main = $this->createUser();
        $linked = $this->createUser(mainAccount: $main);

        $main->forceDelete();

        $this->assertModelExists($linked);
        $this->assertTrue($linked->fresh()->isMainAccount());
    }

    public function testMainAccountCanImpersonateItsLinkedAccounts(): void
    {
        $main = $this->createUser($this->createOrganization());
        $linked = $this->createUser($this->createOrganization(), $main);
        $sibling = $this->createUser($this->createOrganization(), $main);
        $stranger = $this->createUser($this->createOrganization());

        $this->assertTrue($main->canImpersonateUser($linked));
        $this->assertTrue($linked->canBeImpersonatedByUser($main));

        $this->assertFalse($linked->canImpersonateUser($main));
        $this->assertFalse($linked->canImpersonateUser($sibling));
        $this->assertFalse($main->canImpersonateUser($stranger));
        $this->assertFalse($stranger->canImpersonateUser($linked));
    }

    public function testSuperAdminCanImpersonateAnyUser(): void
    {
        Role::create(['name' => UserRole::SuperAdmin->value]);

        $superAdmin = $this->createUser();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $user = $this->createUser($this->createOrganization());

        $this->assertTrue($superAdmin->canImpersonateUser($user));
        $this->assertTrue($user->canBeImpersonatedByUser($superAdmin));
    }
}
