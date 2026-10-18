<?php

namespace Core\Tests\Unit\Policies;

use Core\Enums\UserRole;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\RestoreRequest;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\CrudRequest;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Policies\Interfaces\LimitsCUDInterface;
use Core\Policies\Traits\LimitsCUD;
use Core\Repositories\PermissionRepository;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class CrudPolicyTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Policies
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class CrudPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Policy of users, which is checked.
     *
     * @var CrudPolicy
     */
    private CrudPolicy $policy;

    /**
     * Create the policy of users.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new class extends CrudPolicy
        {
            protected BaseModel|string $model = User::class;
        };
    }

    /**
     * Create a user with the role, if it is given, in the organization, if it is given.
     *
     * @param UserRole|string|null $role
     * @param Organization|null $organization
     *
     * @return User
     */
    private function createUser(UserRole|string|null $role = null, ?Organization $organization = null): User
    {
        $user = User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);

        if ($role) {
            $user->assignRole(Role::findOrCreate($role instanceof UserRole ? $role->value : $role));
        }

        return $user;
    }

    /**
     * Give the role the permissions for the abilities on users.
     *
     * @param UserRole|string $role
     * @param string ...$abilities
     *
     * @return void
     */
    private function grant(UserRole|string $role, string ...$abilities): void
    {
        $permissions = app(PermissionRepository::class)->create(User::class, ...$abilities);

        Role::findOrCreate($role instanceof UserRole ? $role->value : $role)->givePermissionTo($permissions);
    }

    /**
     * Create a CRUD request of the class, which is made by the user.
     *
     * @template T of CrudRequest
     *
     * @param class-string<T> $class
     * @param User|null $user
     * @param array $data
     *
     * @return T
     */
    private function request(string $class, ?User $user, array $data = []): CrudRequest
    {
        $request = $class::create('/users', 'POST', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Test that a super admin needs no permissions, even to delete permanently.
     *
     * @return void
     */
    public function testSuperAdminNeedsNoPermissions(): void
    {
        $superAdmin = $this->createUser(UserRole::SuperAdmin);
        $target = $this->createUser();

        $this->assertTrue($this->policy->determine($this->request(StoreRequest::class, $superAdmin)));
        $this->assertTrue(
            $this->policy->determine(
                $this->request(DestroyRequest::class, $superAdmin, ['force' => true])->setId($target->id)
            )
        );
    }

    /**
     * Test that a user needs the permission for the ability.
     *
     * @return void
     */
    public function testUserNeedsThePermission(): void
    {
        $user = $this->createUser(UserRole::User);

        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, $user)));

        $this->grant(UserRole::User, 'create');

        $this->assertTrue($this->policy->determine($this->request(StoreRequest::class, $user->fresh())));
    }

    /**
     * Test that a guest may view, but can't create, even with the permission.
     *
     * @return void
     */
    public function testGuestCannotCreateEvenWithThePermission(): void
    {
        $this->grant(UserRole::Guest, 'viewAny', 'create');

        $guest = $this->createUser(UserRole::Guest);

        $this->assertTrue($this->policy->determine($this->request(IndexRequest::class, $guest)));
        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, $guest)));
    }

    /**
     * Test that a role, which the application defines, may change models with the permission, as the default
     * roles may.
     *
     * @return void
     */
    public function testRoleOfTheApplicationNeedsThePermission(): void
    {
        $accountant = $this->createUser('accountant');

        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, $accountant)));

        $this->grant('accountant', 'create');

        $this->assertTrue($this->policy->determine($this->request(StoreRequest::class, $accountant->fresh())));
    }

    /**
     * Test that a request without a user is denied, whatever the permissions.
     *
     * @return void
     */
    public function testRequestWithoutUserIsDenied(): void
    {
        $this->grant(UserRole::User, 'all');

        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, null)));
        $this->assertFalse($this->policy->determine($this->request(IndexRequest::class, null)));
    }

    /**
     * Test that a target outside of the user's scope isn't found.
     *
     * @return void
     */
    public function testTargetOutsideOfTheUserScopeIsNotFound(): void
    {
        $this->grant(UserRole::User, 'all');

        $user = $this->createUser(UserRole::User, Organization::query()->create(['slug' => 'a', 'name' => 'A']));
        $stranger = $this->createUser(null, Organization::query()->create(['slug' => 'b', 'name' => 'B']));

        $this->expectException(ModelNotFoundException::class);

        $this->policy->determine($this->request(UpdateRequest::class, $user)->setId($stranger->id));
    }

    /**
     * Test that a permanent delete needs its own permission, which `all` doesn't include.
     *
     * @return void
     */
    public function testForceDeleteIsAuthorizedOnItsOwn(): void
    {
        $this->grant(UserRole::User, 'all');

        $user = $this->createUser(UserRole::User);

        $delete = $this->request(DestroyRequest::class, $user)->setId($user->id);
        $forceDelete = $this->request(DestroyRequest::class, $user, ['force' => true])->setId($user->id);

        $this->assertSame('forceDelete', $forceDelete->getAbility());
        $this->assertTrue($this->policy->determine($delete));
        $this->assertFalse($this->policy->determine($forceDelete));
    }

    /**
     * Test that `force=false` is a plain delete.
     *
     * @return void
     */
    public function testForceFalseIsAPlainDelete(): void
    {
        $request = $this->request(DestroyRequest::class, null, ['force' => 'false']);

        $this->assertFalse($request->force());
        $this->assertSame('delete', $request->getAbility());
    }

    /**
     * Test that an ability, which the policy has no method for, is denied.
     *
     * @return void
     */
    public function testAbilityWithoutMethodIsDenied(): void
    {
        $superAdmin = $this->createUser(UserRole::SuperAdmin);

        $this->assertFalse($this->policy->__call('export', [$superAdmin]));
    }

    /**
     * Test that permissions are defined, once they are created.
     *
     * @return void
     */
    public function testPermissionsAreDefinedOnceCreated(): void
    {
        $this->assertFalse($this->policy->arePermissionsDefined(['view', 'update']));

        app(PermissionRepository::class)->create(User::class, 'view', 'update');

        $this->assertTrue($this->policy->arePermissionsDefined(['view', 'update']));
        $this->assertFalse($this->policy->arePermissionsDefined(['view', 'forceDelete']));
    }

    /**
     * Test that LimitsCUD lets only super admins create, update, delete and restore.
     *
     * @return void
     */
    public function testLimitsCUDAllowsOnlySuperAdminsToChange(): void
    {
        $policy = new class extends CrudPolicy implements LimitsCUDInterface
        {
            use LimitsCUD;

            protected BaseModel|string $model = User::class;
        };

        $this->grant(UserRole::Admin, 'all', 'restore');

        $organization = Organization::factory()->create();
        $admin = $this->createUser(UserRole::Admin, $organization);
        $superAdmin = $this->createUser(UserRole::SuperAdmin);
        $trashed = $this->createUser(null, $organization);
        $trashed->delete();

        $this->assertTrue($policy->determine($this->request(IndexRequest::class, $admin)));
        $this->assertFalse($policy->determine($this->request(StoreRequest::class, $admin)));
        $this->assertTrue($policy->determine($this->request(StoreRequest::class, $superAdmin)));
        $this->assertFalse($policy->determine($this->request(RestoreRequest::class, $admin)->setId($trashed->id)));
        $this->assertTrue($policy->determine($this->request(RestoreRequest::class, $superAdmin)->setId($trashed->id)));
    }

    /**
     * Test that restoring needs its own permission, and finds only a soft-deleted target.
     *
     * @return void
     */
    public function testRestoreNeedsItsOwnPermissionAndATrashedTarget(): void
    {
        $this->grant(UserRole::User, 'all');
        $this->grant('restorer', 'all', 'restore');

        $organization = Organization::factory()->create();
        $user = $this->createUser(UserRole::User, $organization);
        $restorer = $this->createUser('restorer', $organization);
        $trashed = $this->createUser(null, $organization);
        $trashed->delete();

        $restoreBy = fn (User $actor) => $this->request(RestoreRequest::class, $actor)->setId($trashed->id);

        $this->assertFalse($this->policy->determine($restoreBy($user)));
        $this->assertTrue($this->policy->determine($restoreBy($restorer)));

        $this->expectException(ModelNotFoundException::class);

        $this->policy->determine($this->request(RestoreRequest::class, $restorer)->setId($user->id));
    }
}
