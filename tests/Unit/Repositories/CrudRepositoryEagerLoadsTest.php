<?php

namespace Core\Tests\Unit\Repositories;

use Core\Http\Resources\BaseResource;
use Core\Models\BaseModel;
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

    public function testGetValidatedIncludesReturnsEmptyWhenResourceClassIsNotBaseResource(): void
    {
        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected string $resourceClass = '';
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $includes = $repo->getValidatedIncludes($request);

        $this->assertEmpty($includes);
    }

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
