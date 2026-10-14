<?php

namespace Core\Tests\Unit\Policies;

use Core\Enums\UserRole;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
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

    private CrudPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new class extends CrudPolicy
        {
            protected BaseModel|string $model = User::class;
        };
    }

    private function createUser(?UserRole $role = null, ?Organization $organization = null): User
    {
        $user = User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);

        if ($role) {
            $user->assignRole(Role::findOrCreate($role->value));
        }

        return $user;
    }

    private function grant(UserRole $role, string ...$abilities): void
    {
        $permissions = app(PermissionRepository::class)->create(User::class, ...$abilities);

        Role::findOrCreate($role->value)->givePermissionTo($permissions);
    }

    /**
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

    public function testUserNeedsThePermission(): void
    {
        $user = $this->createUser(UserRole::User);

        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, $user)));

        $this->grant(UserRole::User, 'create');

        $this->assertTrue($this->policy->determine($this->request(StoreRequest::class, $user->fresh())));
    }

    public function testGuestCannotCreateEvenWithThePermission(): void
    {
        $this->grant(UserRole::Guest, 'viewAny', 'create');

        $guest = $this->createUser(UserRole::Guest);

        $this->assertTrue($this->policy->determine($this->request(IndexRequest::class, $guest)));
        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, $guest)));
    }

    public function testRequestWithoutUserIsDenied(): void
    {
        $this->grant(UserRole::User, 'all');

        $this->assertFalse($this->policy->determine($this->request(StoreRequest::class, null)));
        $this->assertFalse($this->policy->determine($this->request(IndexRequest::class, null)));
    }

    public function testTargetOutsideOfTheUserScopeIsNotFound(): void
    {
        $this->grant(UserRole::User, 'all');

        $user = $this->createUser(UserRole::User, Organization::query()->create(['slug' => 'a', 'name' => 'A']));
        $stranger = $this->createUser(null, Organization::query()->create(['slug' => 'b', 'name' => 'B']));

        $this->expectException(ModelNotFoundException::class);

        $this->policy->determine($this->request(UpdateRequest::class, $user)->setId($stranger->id));
    }

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

    public function testForceFalseIsAPlainDelete(): void
    {
        $request = $this->request(DestroyRequest::class, null, ['force' => 'false']);

        $this->assertFalse($request->force());
        $this->assertSame('delete', $request->getAbility());
    }

    public function testAbilityWithoutMethodIsDenied(): void
    {
        $superAdmin = $this->createUser(UserRole::SuperAdmin);

        $this->assertFalse($this->policy->__call('export', [$superAdmin]));
    }

    public function testPermissionsAreDefinedOnceCreated(): void
    {
        $this->assertFalse($this->policy->arePermissionsDefined(['view', 'update']));

        app(PermissionRepository::class)->create(User::class, 'view', 'update');

        $this->assertTrue($this->policy->arePermissionsDefined(['view', 'update']));
        $this->assertFalse($this->policy->arePermissionsDefined(['view', 'forceDelete']));
    }

    public function testLimitsCUDAllowsOnlySuperAdminsToChange(): void
    {
        $policy = new class extends CrudPolicy implements LimitsCUDInterface
        {
            use LimitsCUD;

            protected BaseModel|string $model = User::class;
        };

        $this->grant(UserRole::Admin, 'all');

        $admin = $this->createUser(UserRole::Admin);
        $superAdmin = $this->createUser(UserRole::SuperAdmin);

        $this->assertTrue($policy->determine($this->request(IndexRequest::class, $admin)));
        $this->assertFalse($policy->determine($this->request(StoreRequest::class, $admin)));
        $this->assertTrue($policy->determine($this->request(StoreRequest::class, $superAdmin)));
    }
}
