<?php

namespace Core\Tests\Feature\Http\Controllers;

use BadMethodCallException;
use Core\Enums\UserRole;
use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Repositories\CrudRepository;
use Core\Repositories\PermissionRepository;
use Core\Repositories\UserRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Class CrudControllerTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class CrudControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Controller of the users, with its own store and update requests, which the tests call.
     *
     * @var CrudController
     */
    private CrudController $controller;

    /**
     * Signed-in user of the organization, whose role may do everything with users, except restoring and force
     * deleting them.
     *
     * @var User
     */
    private User $user;

    /**
     * User of the same organization, who is in the scope of the signed-in user.
     *
     * @var User
     */
    private User $colleague;

    /**
     * User of another organization, who is out of the scope of the signed-in user.
     *
     * @var User
     */
    private User $stranger;

    /**
     * Register the routes of a controller of users, with its own store and update requests, give the user role the
     * permission to do everything with users, and create the user, the colleague in the same organization and the
     * stranger in another one.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $store = get_class(new class extends StoreRequest
        {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                    'email' => ['required', 'email'],
                    'password' => ['required', 'string', 'min:8'],
                ];
            }
        });

        $update = get_class(new class extends UpdateRequest
        {
            public function rules(): array
            {
                return [
                    'name' => ['sometimes', 'string'],
                ];
            }
        });

        $policy = new class extends CrudPolicy
        {
            protected BaseModel|string $model = User::class;
        };

        $operations = ['store' => $store, 'update' => $update];

        $this->controller = new class($policy, new UserRepository, $operations) extends CrudController
        {
            protected string $resourceClass = BaseResource::class;

            protected string $collectionClass = BaseCollection::class;

            public function __construct(CrudPolicy $policy, CrudRepository $repository, array $operations)
            {
                parent::__construct($policy, $repository);

                $this->operations = array_merge($this->operations, $operations);
            }
        };

        $class = get_class($this->controller);
        $this->app->instance($class, $this->controller);

        Route::get('/users', [$class, 'index']);
        Route::get('/users/{id}', [$class, 'show']);
        Route::post('/users', [$class, 'store']);
        Route::patch('/users/{id}', [$class, 'update']);
        Route::delete('/users/{id}', [$class, 'destroy']);

        Role::findOrCreate(UserRole::User->value)->givePermissionTo(
            app(PermissionRepository::class)->create(User::class, 'all')
        );

        $organization = Organization::query()->create(['slug' => 'acme', 'name' => 'Acme']);

        $this->user = $this->createUser('user@example.com', $organization, UserRole::User);
        $this->colleague = $this->createUser('colleague@example.com', $organization);
        $this->stranger = $this->createUser(
            'stranger@example.com',
            Organization::query()->create(['slug' => 'other', 'name' => 'Other'])
        );
    }

    /**
     * Create a user with the given email, in the organization and with the role, if they are given.
     *
     * @param string $email
     * @param Organization|null $organization
     * @param UserRole|null $role
     *
     * @return User
     */
    private function createUser(string $email, ?Organization $organization = null, ?UserRole $role = null): User
    {
        $user = User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => 'Name of ' . $email,
            'email' => $email,
            'password' => 'password123',
        ]);

        if ($role) {
            $user->assignRole(Role::findOrCreate($role->value));
        }

        return $user;
    }

    /**
     * Test that the index answers with a page of 100 of the users in the user's scope, without the stranger and without
     * their passwords.
     *
     * @return void
     */
    public function testIndexReturnsAPageOfTheModelsInScope(): void
    {
        $response = $this->actingAs($this->user)->getJson('/users');

        $response->assertOk()
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('data.0.email', 'user@example.com')
            ->assertJsonPath('data.1.email', 'colleague@example.com')
            ->assertJsonMissingPath('data.0.password');
    }

    /**
     * Test that the index applies the filter and the page size, which the query asks for.
     *
     * @return void
     */
    public function testIndexAppliesFiltersAndPagination(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/users?filter[email][like]=colleague%25&page[size]=1'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'colleague@example.com')
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * Test that the index answers with 422 to a filter by a field, which cannot be filtered, and to an include, which
     * is not allowed.
     *
     * @return void
     */
    public function testIndexAnswersBadFiltersAndIncludesWith422(): void
    {
        $this->actingAs($this->user)
            ->getJson('/users?filter[password][eq]=secret')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.password']);

        $this->actingAs($this->user)
            ->getJson('/users?include=organization')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include']);
    }

    /**
     * Test that showing a user in the user's scope answers with that user.
     *
     * @return void
     */
    public function testShowReturnsTheModelInScope(): void
    {
        $this->actingAs($this->user)
            ->getJson('/users/' . $this->colleague->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->colleague->id);
    }

    /**
     * Test that showing a user out of the user's scope answers with 404.
     *
     * @return void
     */
    public function testShowAnswersAModelOutOfScopeWith404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/users/' . $this->stranger->id)
            ->assertNotFound();
    }

    /**
     * Test that storing valid data answers with 201 and saves the new user.
     *
     * @return void
     */
    public function testStoreCreatesTheModel(): void
    {
        $response = $this->actingAs($this->user)->postJson('/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Created')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    /**
     * Test that storing and updating with an append or an include, which the resource doesn't allow, answer with
     * 422 before the model is created or changed.
     *
     * @return void
     */
    public function testStoreAndUpdateRefuseNotAllowedAppendsAndIncludesFirst(): void
    {
        $this->actingAs($this->user)
            ->postJson('/users?append=locale', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['append']);

        $this->patchJson('/users/' . $this->colleague->id . '?include=organization', ['name' => 'Renamed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include']);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertNotSame('Renamed', $this->colleague->fresh()->name);
    }

    /**
     * Test that storing data without the required fields answers with 422, naming the missing ones.
     *
     * @return void
     */
    public function testStoreAnswersInvalidDataWith422(): void
    {
        $this->actingAs($this->user)
            ->postJson('/users', ['name' => 'New User'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test that storing invalid data answers with 403, not 422, to a guest and to a user without the permission, as
     * authorization comes before validation.
     *
     * @return void
     */
    public function testStoreIsAuthorizedBeforeValidation(): void
    {
        $this->postJson('/users', ['name' => 'New User'])
            ->assertForbidden();

        $withoutPermission = $this->createUser('guest@example.com', null, UserRole::Guest);

        $this->actingAs($withoutPermission)
            ->postJson('/users', ['name' => 'New User'])
            ->assertForbidden();
    }

    /**
     * Test that updating a user in scope answers with the changed user and saves the change.
     *
     * @return void
     */
    public function testUpdateChangesTheModel(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->colleague->id, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('message', 'Updated')
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $this->colleague->fresh()->name);
    }

    /**
     * Test that updating with an empty body answers with 422, as it has no fields to update.
     *
     * @return void
     */
    public function testUpdateAnswersNothingToUpdateWith422(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->colleague->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields']);
    }

    /**
     * Test that updating a user out of the user's scope answers with 404.
     *
     * @return void
     */
    public function testUpdateAnswersAModelOutOfScopeWith404(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->stranger->id, ['name' => 'Renamed'])
            ->assertNotFound();
    }

    /**
     * Test that destroying a user soft deletes them, so they are not shown any more.
     *
     * @return void
     */
    public function testDestroySoftDeletesTheModel(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/users/' . $this->colleague->id)
            ->assertOk()
            ->assertJsonPath('message', 'Deleted');

        $this->assertSoftDeleted($this->colleague);

        $this->actingAs($this->user)
            ->getJson('/users/' . $this->colleague->id)
            ->assertNotFound();
    }

    /**
     * Test that a soft-deleted user cannot be destroyed again, but can be force destroyed by a super admin, which
     * removes them from the database.
     *
     * @return void
     */
    public function testForceDestroyRemovesASoftDeletedModel(): void
    {
        $this->colleague->delete();

        $superAdmin = $this->createUser('super@example.com', null, UserRole::SuperAdmin);

        $this->actingAs($superAdmin)
            ->deleteJson('/users/' . $this->colleague->id)
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->deleteJson('/users/' . $this->colleague->id . '?force=1')
            ->assertOk();

        $this->assertModelMissing($this->colleague);
    }

    /**
     * Test that force destroying answers with 403 to a user, whose role may do everything else with users, while a
     * super admin may do it.
     *
     * @return void
     */
    public function testForceDestroyNeedsItsOwnPermission(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/users/' . $this->colleague->id . '?force=1')
            ->assertForbidden();

        $this->assertModelExists($this->colleague);

        $superAdmin = $this->createUser('super@example.com', null, UserRole::SuperAdmin);

        $this->actingAs($superAdmin)
            ->deleteJson('/users/' . $this->colleague->id . '?force=1')
            ->assertOk();

        $this->assertModelMissing($this->colleague);
    }

    /**
     * Test that calling a method, which is no operation of the controller, throws a bad method call exception.
     *
     * @return void
     */
    public function testMethodThatIsNoOperationThrows(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->controller->__call('export', []);
    }
}
