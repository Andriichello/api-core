<?php

namespace Core\Tests\Feature\Http\Controllers;

use BadMethodCallException;
use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Repositories\CrudRepository;
use Core\Repositories\UserRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Class CrudControllerRoutesTest.
 *
 * Registering the routes of a CRUD controller, whose operations are its methods, so the attributes, which are
 * chained before the route (middleware, prefix, name), apply to it.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class CrudControllerRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Make a controller of users, which handles the operations, and whose policy allows them all.
     *
     * @param array<string, class-string<CrudRequest>>|null $operations All of its operations, the default ones when
     *                                                                  null, plus an `export` one.
     *
     * @return CrudController
     */
    private function makeController(?array $operations = null): CrudController
    {
        $policy = new class extends CrudPolicy
        {
            public function determine(CrudRequest $request): Response|bool
            {
                return true;
            }
        };

        return new class($policy, new UserRepository, $operations) extends CrudController
        {
            protected string $resourceClass = BaseResource::class;

            protected string $collectionClass = BaseCollection::class;

            public function __construct(CrudPolicy $policy, CrudRepository $repository, ?array $operations)
            {
                parent::__construct($policy, $repository);

                $this->operations = $operations ?? [
                    ...$this->operations,
                    'store' => get_class(new class extends StoreRequest
                    {
                        public function rules(): array
                        {
                            return ['name' => ['required', 'string']];
                        }
                    }),
                    'export' => IndexRequest::class,
                ];
            }

            protected function handleExport(IndexRequest $request): ApiResponse
            {
                return ApiResponse::ok(['data' => 'Exported']);
            }
        };
    }

    /**
     * Make the controller and register it in the container, so its routes use it.
     *
     * @return class-string<CrudController>
     */
    private function registerController(): string
    {
        $controller = $this->makeController();
        $class = get_class($controller);

        $this->app->instance($class, $controller);

        return $class;
    }

    /**
     * Test that the middleware, prefix and name, which are chained before a route of an operation, apply to it.
     *
     * @return void
     */
    public function testChainedAttributesApplyToOperations(): void
    {
        $class = $this->registerController();

        Route::middleware('auth')->prefix('chained')->name('chained.users.index')->get('users', [$class, 'index']);
        Route::middleware('auth')->prefix('chained')->post('users', [$class, 'store']);

        $this->getJson('/chained/users')->assertUnauthorized();
        $this->postJson('/chained/users', ['name' => 'Bob'])->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson('/chained/users')->assertOk();
        $this->assertSame(url('/chained/users'), route('chained.users.index'));
    }

    /**
     * Test that an operation, which the controller doesn't handle, can't be called through its method.
     *
     * @return void
     */
    public function testOperationWhichIsNotHandledThrows(): void
    {
        $controller = $this->makeController(['index' => IndexRequest::class]);

        $this->expectException(BadMethodCallException::class);

        $controller->destroy(Request::create('/users/1', 'DELETE'));
    }

    /**
     * Test that an extra operation is handled through `__call()`, and its route, which is registered in a group,
     * gets the group's middleware.
     *
     * @return void
     */
    public function testExtraOperationIsHandled(): void
    {
        $class = $this->registerController();

        Route::middleware('auth')->group(fn () => Route::get('export', [$class, 'export']));

        $this->getJson('/export')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/export')
            ->assertOk()
            ->assertJsonPath('data', 'Exported');
    }
}
