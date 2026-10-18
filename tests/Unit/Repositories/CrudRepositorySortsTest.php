<?php

namespace Core\Tests\Unit\Repositories;

use Core\Models\BaseModel;
use Core\Models\User;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class CrudRepositorySortsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Repositories
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
class CrudRepositorySortsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Repository of users, which allows sorts by their queryable columns.
     *
     * @var CrudRepository
     */
    private CrudRepository $repository;

    /**
     * Create the repository of users, which allows sorts by their queryable columns.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };
    }

    /**
     * Create a request to list the users with the given parameters, which is sent by a newly created user.
     *
     * @param array<string, mixed> $data
     *
     * @return Request
     */
    private function createRequestWithUser(array $data = []): Request
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
        $request = Request::create('/users', 'GET', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Test that a sort by a column is applied in the ascending order.
     *
     * @return void
     */
    public function testAppliesSingleAscendingSort(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => 'name',
        ]);

        $query = $this->repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.name', $orders[0]['column']);
        $this->assertEquals('asc', $orders[0]['direction']);
    }

    /**
     * Test that a sort by a column, which starts with a minus, is applied in the descending order.
     *
     * @return void
     */
    public function testAppliesSingleDescendingSort(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => '-name',
        ]);

        $query = $this->repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.name', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }

    /**
     * Test that the sorts, which are separated by commas, are all applied, each in its own direction.
     *
     * @return void
     */
    public function testApplesMultipleSorts(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => 'name,-created_at',
        ]);

        $query = $this->repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(2, $orders);
        $this->assertEquals('users.name', $orders[0]['column']);
        $this->assertEquals('asc', $orders[0]['direction']);
        $this->assertEquals('users.created_at', $orders[1]['column']);
        $this->assertEquals('desc', $orders[1]['direction']);
    }

    /**
     * Test that the default sort by the identifier in the ascending order is applied, when no sort is requested.
     *
     * @return void
     */
    public function testApplesDefaultSortWhenNoSortProvided(): void
    {
        $request = $this->createRequestWithUser([]);

        $query = $this->repository->builder($request);

        $orders = $query->getQuery()->orders;
        // Default should be 'id' => 'asc'
        $this->assertCount(1, $orders);
        $this->assertEquals('users.id', $orders[0]['column']);
        $this->assertEquals('asc', $orders[0]['direction']);
    }

    /**
     * Test that the repository's own default sort is applied, when no sort is requested.
     *
     * @return void
     */
    public function testApplesCustomDefaultSort(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $defaultSorts = ['created_at' => 'desc'];
        };
        $request = $this->createRequestWithUser([]);

        $query = $repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }

    /**
     * Test that a sort, which is allowed with a column, is applied to that column instead of its name.
     *
     * @return void
     */
    public function testAppliesSortWithCustomColumn(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedSorts = [
                'org_id' => ['organization_id'],
            ];
        };
        $request = $this->createRequestWithUser([
            'sort' => 'org_id',
        ]);

        $query = $repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        // Should use organization_id column instead of org_id
        $this->assertStringContainsString('organization_id', $orders[0]['column']);
    }

    /**
     * Test that a sort, which is allowed with a column and a table, is applied to the column of that table.
     *
     * @return void
     */
    public function testAppliesSortWithTablePrefix(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedSorts = [
                'org_name' => ['name', 'organizations'],
            ];
        };
        $request = $this->createRequestWithUser([
            'sort' => 'org_name',
        ]);

        $query = $repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        // Should include table prefix
        $this->assertStringContainsString('organizations.name', $orders[0]['column']);
    }

    /**
     * Test that a sort, which is not listed, is refused, when not all sorts are allowed.
     *
     * @return void
     */
    public function testThrowsExceptionForDisallowedSort(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sorting by the \'password\' is not allowed');

        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected bool $allowAllSorts = false;

            protected array $allowedSorts = ['id', 'email', 'name', 'created_at'];
        };
        $request = $this->createRequestWithUser([
            'sort' => 'password',
        ]);

        $repository->builder($request);
    }

    /**
     * Test that a sort by an unknown column is refused, even when all sorts are allowed.
     *
     * @return void
     */
    public function testThrowsExceptionForUnknownSortWhenAllSortsAreAllowed(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sorting by the \'unknown_column\' is not allowed');

        $request = $this->createRequestWithUser([
            'sort' => 'name,unknown_column',
        ]);

        $this->repository->builder($request);
    }

    /**
     * Test that the sorts are applied in the order, in which they are requested.
     *
     * @return void
     */
    public function testKeepsRequestedSortOrder(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => '-created_at,name,id',
        ]);

        $query = $this->repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertSame(
            [
                ['column' => 'users.created_at', 'direction' => 'desc'],
                ['column' => 'users.name', 'direction' => 'asc'],
                ['column' => 'users.id', 'direction' => 'asc'],
            ],
            $orders
        );
    }

    /**
     * Test that a sort, which is listed, is applied, when not all sorts are allowed.
     *
     * @return void
     */
    public function testAppliesSortFromExplicitAllowedList(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected bool $allowAllSorts = false;

            protected array $allowedSorts = ['id', 'email', 'name', 'created_at'];
        };
        $request = $this->createRequestWithUser([
            'sort' => 'name', // This is allowed
        ]);

        $query = $repository->builder($request);

        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.name', $orders[0]['column']);
    }

    /**
     * Test that the sorts and the filters of the same request are both applied.
     *
     * @return void
     */
    public function testCombinesSortsAndFilters(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => '-created_at',
            'filter' => [
                'email' => ['like' => '%@example.com'],
            ],
        ]);

        $query = $this->repository->builder($request);

        // Check sort is applied
        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);

        // Check filter is applied
        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" LIKE \'%@example.com\'', $sql);
    }

    /**
     * Test that sorting by a hidden column, such as the password, answers with 422, naming the allowed sorts.
     *
     * @return void
     */
    public function testHiddenColumnsAreNotSortableByDefault(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => 'password',
        ]);

        try {
            $this->repository->builder($request);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['sort'][0];

            $this->assertStringStartsWith("Sorting by the 'password' is not allowed. Allowed sorts: ", $message);
            $this->assertStringContainsString('email', $message);

            return;
        }

        $this->fail('The sort did not answer with 422.');
    }

    /**
     * Test that a hidden column can be sorted by, when it is listed among the allowed sorts.
     *
     * @return void
     */
    public function testHiddenColumnCanBeAllowedExplicitly(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedSorts = ['password'];
        };
        $request = $this->createRequestWithUser([
            'sort' => '-password',
        ]);

        $orders = $repository->builder($request)->getQuery()->orders;

        $this->assertEquals('users.password', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }

    /**
     * Test that a sort parameter, which is not a string, answers with 422.
     *
     * @return void
     */
    public function testSortParameterThatIsNotAStringAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'sort' => ['name'],
        ]);

        try {
            $this->repository->builder($request);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sort', $exception->errors());

            return;
        }

        $this->fail('The sort did not answer with 422.');
    }

    /**
     * Test that the builder applies the default sort, when it is not given a request and the current one has no sorts.
     *
     * @return void
     */
    public function testBuilderWithoutRequestApplesDefaultSort(): void
    {
        $query = $this->repository->builder();

        // Falls back to the current request, which has no sorts
        $orders = $query->getQuery()->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals('users.id', $orders[0]['column']);
        $this->assertEquals('asc', $orders[0]['direction']);
    }

    /**
     * Test that sorts listed with their column are allowed, when not all sorts are, and only they are.
     *
     * @return void
     */
    public function testAllowsKeyedSortsWhenNotAllSortsAreAllowed(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected bool $allowAllSorts = false;

            protected array $allowedSorts = [
                'joined' => ['created_at'],
                'name',
            ];
        };

        $orders = $repository->builder($this->createRequestWithUser(['sort' => '-joined,name']))
            ->getQuery()
            ->orders;

        $this->assertSame(
            [
                ['column' => 'users.created_at', 'direction' => 'desc'],
                ['column' => 'users.name', 'direction' => 'asc'],
            ],
            $orders
        );

        try {
            $repository->builder(Request::create('/users', 'GET', ['sort' => 'email']));
        } catch (ValidationException $exception) {
            $this->assertSame(
                ["Sorting by the 'email' is not allowed. Allowed sorts: joined, name."],
                $exception->errors()['sort']
            );

            return;
        }

        $this->fail('The sort did not answer with 422.');
    }

    /**
     * Test that no sort is allowed, when not all sorts are and none are listed.
     *
     * @return void
     */
    public function testAllowsNoSortsWhenNoneAreListedAndNotAllSortsAreAllowed(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected bool $allowAllSorts = false;
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Sorting by the 'email' is not allowed. No sorts are allowed.");

        $repository->builder(Request::create('/users', 'GET', ['sort' => 'email']));
    }
}
