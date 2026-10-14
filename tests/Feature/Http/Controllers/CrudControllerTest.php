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

    private CrudController $controller;

    private User $user;

    private User $colleague;

    private User $stranger;

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

    public function testShowReturnsTheModelInScope(): void
    {
        $this->actingAs($this->user)
            ->getJson('/users/' . $this->colleague->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->colleague->id);
    }

    public function testShowAnswersAModelOutOfScopeWith404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/users/' . $this->stranger->id)
            ->assertNotFound();
    }

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

    public function testStoreAnswersInvalidDataWith422(): void
    {
        $this->actingAs($this->user)
            ->postJson('/users', ['name' => 'New User'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function testStoreIsAuthorizedBeforeValidation(): void
    {
        $this->postJson('/users', ['name' => 'New User'])
            ->assertForbidden();

        $withoutPermission = $this->createUser('guest@example.com', null, UserRole::Guest);

        $this->actingAs($withoutPermission)
            ->postJson('/users', ['name' => 'New User'])
            ->assertForbidden();
    }

    public function testUpdateChangesTheModel(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->colleague->id, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('message', 'Updated')
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $this->colleague->fresh()->name);
    }

    public function testUpdateAnswersNothingToUpdateWith422(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->colleague->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields']);
    }

    public function testUpdateAnswersAModelOutOfScopeWith404(): void
    {
        $this->actingAs($this->user)
            ->patchJson('/users/' . $this->stranger->id, ['name' => 'Renamed'])
            ->assertNotFound();
    }

    public function testDestroyDeletesTheModel(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/users/' . $this->colleague->id)
            ->assertOk()
            ->assertJsonPath('message', 'Deleted');

        $this->assertModelMissing($this->colleague);
    }

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

    public function testMethodThatIsNoOperationThrows(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->controller->__call('export', []);
    }
}
