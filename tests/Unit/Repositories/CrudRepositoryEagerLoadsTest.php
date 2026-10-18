<?php

namespace Core\Tests\Unit\Repositories;

use Core\Http\Resources\BaseResource;
use Core\Http\Resources\UserCollection;
use Core\Models\AuditLog;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class CrudRepositoryEagerLoadsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Repositories
 */
class CrudRepositoryEagerLoadsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get a repository of users, which validates includes against the given resource.
     *
     * @param string $resourceClass
     *
     * @return CrudRepository
     */
    private function usersRepository(string $resourceClass): CrudRepository
    {
        return new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            /**
             * Create a repository, which validates includes against the given resource.
             *
             * @param string $resourceClass
             */
            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };
    }

    /**
     * Test that the validated includes are empty, when the request has no include parameter.
     *
     * @return void
     */
    public function testGetValidatedIncludesReturnsEmptyWhenRequestHasNoInclude(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $includes = $repo->getValidatedIncludes(Request::create('/'));

        $this->assertEmpty($includes);
    }

    /**
     * Test that the validated includes are empty, when the repository's resource class is not a base resource.
     *
     * @return void
     */
    public function testGetValidatedIncludesReturnsEmptyWhenResourceClassIsNotBaseResource(): void
    {
        $repo = $this->usersRepository('');

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $includes = $repo->getValidatedIncludes($request);

        $this->assertEmpty($includes);
    }

    /**
     * Test that an include, which the resource allows, is among the validated includes.
     *
     * @return void
     */
    public function testGetValidatedIncludesReturnsAllowedInclude(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $includes = $repo->getValidatedIncludes($request);

        $this->assertContains('organization', $includes);
    }

    /**
     * Test that the includes, which are separated by commas and allowed by the resource, are all validated.
     *
     * @return void
     */
    public function testGetValidatedIncludesReturnsMultipleAllowedIncludes(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization', 'roles'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $request = Request::create('/', 'GET', ['include' => 'organization,roles']);
        $includes = $repo->getValidatedIncludes($request);

        $this->assertContains('organization', $includes);
        $this->assertContains('roles', $includes);
        $this->assertCount(2, $includes);
    }

    /**
     * Test that validating an include, which the resource does not allow, answers with 422.
     *
     * @return void
     */
    public function testGetValidatedIncludesThrowsExceptionForDisallowedInclude(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $repo->getValidatedIncludes(Request::create('/', 'GET', ['include' => 'roles']));
    }

    /**
     * Test that the builder eager-loads the relation of an include, which the resource allows.
     *
     * @return void
     */
    public function testBuilderAppliesEagerLoadForValidInclude(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $query = $repo->builder(Request::create('/', 'GET', ['include' => 'organization']));

        $this->assertArrayHasKey('organization', $query->getEagerLoads());
    }

    /**
     * Test that the builder eager-loads no relation, when the request has no include parameter.
     *
     * @return void
     */
    public function testBuilderDoesNotApplyEagerLoadWhenNoIncludeInRequest(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $query = $repo->builder(Request::create('/'));

        $this->assertArrayNotHasKey('organization', $query->getEagerLoads());
    }

    /**
     * Test that the builder refuses an include, which the resource does not allow, with 422.
     *
     * @return void
     */
    public function testBuilderThrowsWhenIncludeIsNotAllowedOnResource(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $repo->builder(Request::create('/', 'GET', ['include' => 'roles']));
    }

    /**
     * Test that an include, which is not allowed, answers with 422, naming the allowed includes.
     *
     * @return void
     */
    public function testNotAllowedIncludeAnswers422NamingTheAllowedOnes(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        try {
            $repo->getValidatedIncludes(Request::create('/', 'GET', ['include' => 'roles']));
        } catch (ValidationException $exception) {
            $this->assertEquals(
                ["The 'roles' include is not allowed. Allowed includes: organization."],
                $exception->errors()['include']
            );

            return;
        }

        $this->fail('The include did not answer with 422.');
    }

    /**
     * Test that the builder refuses an include nested under an include, which has no resource, before loading it.
     *
     * @return void
     */
    public function testBuilderRefusesNestedIncludeUnderIncludeWithoutResource(): void
    {
        $repo = $this->usersRepository(get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        }));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(
            "The 'organization.users' include is not allowed. No includes are allowed under 'organization'."
        );

        $repo->builder(Request::create('/', 'GET', ['include' => 'organization.users']));
    }

    /**
     * Test that the builder eager-loads nested includes only as far as the user may list the related models.
     *
     * @return void
     */
    public function testBuilderScopesNestedEagerLoadsByIndexOfRelatedQueries(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $acme->id]);
        $colleague = User::factory()->create(['organization_id' => $acme->id]);
        $linked = User::factory()->create(['organization_id' => $globex->id, 'main_account_id' => $user->id]);

        $resource = new class(null) extends BaseResource
        {
            /**
             * The resource of the organization, which the test sets.
             *
             * @var string|null
             */
            public static ?string $organization = null;

            /**
             * Create a resource, which allows the organization as its resource.
             *
             * @param mixed $resource
             */
            public function __construct(mixed $resource)
            {
                parent::__construct($resource);
                $this->allowedIncludes = ['organization' => self::$organization];
            }
        };

        $resource::$organization = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['users' => UserCollection::class];
        });

        $repo = $this->usersRepository($resource::class);

        $request = Request::create('/', 'GET', ['include' => 'organization.users']);
        $request->setUserResolver(fn () => $user);

        $users = $repo->builder($request)->get()->keyBy('id');

        $this->assertEqualsCanonicalizing([$user->id, $colleague->id, $linked->id], $users->keys()->all());

        // The organization of the linked account isn't listed for the user, so it isn't loaded
        $this->assertNull($users[$linked->id]->getRelation('organization'));
        $this->assertTrue($users[$user->id]->getRelation('organization')->is($acme));
        $this->assertEqualsCanonicalizing(
            [$user->id, $colleague->id],
            $users[$user->id]->getRelation('organization')->getRelation('users')->modelKeys()
        );
    }

    /**
     * Test that scoped eager loads of a to-many relation load only the related models the user may list.
     *
     * @return void
     */
    public function testScopedEagerLoadsLoadOnlyRelatedModelsTheUserMayList(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $acme->id]);
        $linked = User::factory()->create(['organization_id' => $globex->id, 'main_account_id' => $user->id]);
        User::factory()->create(['organization_id' => $globex->id]);

        $repo = $this->usersRepository(BaseResource::class);

        $globex->load($repo->scopedEagerLoads(['users'], $user));
        $this->assertEquals([$linked->id], $globex->users->modelKeys());

        $globex->load($repo->scopedEagerLoads(['users'], null));
        $this->assertTrue($globex->users->isEmpty());
    }

    /**
     * Test that scoped eager loads of a morphTo relation scope the query of each type of the related models.
     *
     * @return void
     */
    public function testScopedEagerLoadsScopeEachTypeOfMorphTo(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $user = User::factory()->create(['organization_id' => $acme->id]);
        $stranger = User::factory()->create(['organization_id' => $globex->id]);

        $entries = collect([$user, $stranger, $acme, $globex])
            ->map(fn (BaseModel $subject) => AuditLog::factory()
                ->onSubject($subject)
                ->create(['organization_id' => $acme->id]));

        $repo = $this->usersRepository(BaseResource::class);

        $loaded = AuditLog::query()
            ->whereKey($entries->pluck('id')->all())
            ->get()
            ->load($repo->scopedEagerLoads(['subject'], $user))
            ->keyBy('id');

        $this->assertTrue($loaded[$entries[0]->id]->getRelation('subject')->is($user));
        $this->assertNull($loaded[$entries[1]->id]->getRelation('subject'));
        $this->assertTrue($loaded[$entries[2]->id]->getRelation('subject')->is($acme));
        $this->assertNull($loaded[$entries[3]->id]->getRelation('subject'));
    }

    /**
     * Test that an include parameter, which is not a string, answers with 422.
     *
     * @return void
     */
    public function testIncludeParameterThatIsNotAStringAnswers422(): void
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $repo = new class($resourceClass) extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function __construct(string $resourceClass)
            {
                $this->resourceClass = $resourceClass;
            }
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The include parameter must be a comma-separated list.');

        $repo->getValidatedIncludes(Request::create('/', 'GET', ['include' => ['organization']]));
    }
}
