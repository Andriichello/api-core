<?php

namespace Core\Tests\Feature\Http\Controllers;

use BadMethodCallException;
use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\RestoreRequest;
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
 * Class CrudControllerRestoreTest.
 *
 * Users restored at `users/{id}/restore` by a controller, which adds the `restore` operation, and at
 * `plain/users/{id}/restore` by one, which doesn't.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class CrudControllerRestoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Organization of the users.
     *
     * @var Organization
     */
    private Organization $organization;

    /**
     * User, whose role may restore users.
     *
     * @var User
     */
    private User $restorer;

    /**
     * Soft-deleted user of the organization.
     *
     * @var User
     */
    private User $trashed;

    /**
     * Create the controllers and their routes, the roles, and the users of the organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $policy = new class extends CrudPolicy
        {
            protected BaseModel|string $model = User::class;
        };

        $restoring = new class($policy, new UserRepository) extends CrudController
        {
            protected string $resourceClass = BaseResource::class;

            protected string $collectionClass = BaseCollection::class;

            public function __construct(CrudPolicy $policy, CrudRepository $repository)
            {
                parent::__construct($policy, $repository);

                $this->operations['restore'] = RestoreRequest::class;
            }
        };

        $plain = new class($policy, new UserRepository) extends CrudController
        {
            protected string $resourceClass = BaseResource::class;

            protected string $collectionClass = BaseCollection::class;
        };

        foreach (['users' => $restoring, 'plain/users' => $plain] as $uri => $controller) {
            $this->app->instance($controller::class, $controller);

            Route::post("$uri/{id}/restore", [$controller::class, 'restore']);
        }

        $permissions = app(PermissionRepository::class);

        Role::findOrCreate('restorer')->givePermissionTo($permissions->create(User::class, 'all', 'restore'));
        Role::findOrCreate('editor')->givePermissionTo($permissions->create(User::class, 'all'));

        $this->organization = Organization::factory()->create();

        $this->restorer = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->restorer->assignRole('restorer');

        $this->trashed = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->trashed->delete();
    }

    /**
     * Test that a soft-deleted model is restored and answered.
     *
     * @return void
     */
    public function testRestoresSoftDeletedModel(): void
    {
        $this->actingAs($this->restorer)
            ->postJson("users/{$this->trashed->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Restored')
            ->assertJsonPath('data.id', $this->trashed->id);

        $this->assertNotSoftDeleted($this->trashed);
    }

    /**
     * Test that only a soft-deleted model in the user's scope is found.
     *
     * @return void
     */
    public function testFindsOnlySoftDeletedModelInScope(): void
    {
        $stranger = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $stranger->delete();

        $this->actingAs($this->restorer)
            ->postJson("users/{$this->restorer->id}/restore")
            ->assertNotFound();

        $this->postJson("users/{$stranger->id}/restore")->assertNotFound();

        $this->assertSoftDeleted($stranger);
    }

    /**
     * Test that restoring needs its own permission.
     *
     * @return void
     */
    public function testRestoreNeedsItsPermission(): void
    {
        $editor = User::factory()->create(['organization_id' => $this->organization->id]);
        $editor->assignRole('editor');

        $this->actingAs($editor)
            ->postJson("users/{$this->trashed->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted($this->trashed);
    }

    /**
     * Test that a controller, which doesn't add the `restore` operation, doesn't handle it.
     *
     * @return void
     */
    public function testControllerWithoutRestoreDoesNotHandleIt(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(BadMethodCallException::class);

        $this->actingAs($this->restorer)->postJson("plain/users/{$this->trashed->id}/restore");
    }
}
