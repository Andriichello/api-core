<?php

namespace Core\Tests\Unit\Repositories;

use Core\Http\Filters\DefaultFilter;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Queries\BaseQuery;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/**
 * Class CrudRepositoryTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Repositories
 */
class CrudRepositoryTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that a single 'eq' filter is applied to the query as a condition on its column.
     *
     * @return void
     */
    public function testAppliesSingleEqFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['eq' => 'test@example.com'],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" = \'test@example.com\'', $sql);
    }

    /**
     * Test that the filters on different columns are all applied to the query.
     *
     * @return void
     */
    public function testApplesMultipleFilters(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['like' => '%@example.com'],
                'name' => ['eq' => 'John Doe'],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" LIKE \'%@example.com\'', $sql);
        $this->assertStringContainsString('"name" = \'John Doe\'', $sql);
    }

    /**
     * Test that the 'in' filter limits the column to the given list of values.
     *
     * @return void
     */
    public function testApplesInFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'id' => ['in' => [1, 2, 3]],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" in (1, 2, 3)', $sql);
    }

    /**
     * Test that the 'null' filter limits the column, which is allowed explicitly, to null values.
     *
     * @return void
     */
    public function testApplesNullFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email_verified_at' => ['null' => true],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = ['email_verified_at'];
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email_verified_at" is null', $sql);
    }

    /**
     * Test that the 'gt' and 'lt' filters on the same column are both applied.
     *
     * @return void
     */
    public function testApplesComparisonFilters(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'id' => [
                    'gt' => 5,
                    'lt' => 10,
                ],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" > 5', $sql);
        $this->assertStringContainsString('"id" < 10', $sql);
    }

    /**
     * Test that the 'not' flag negates the operation of the filter.
     *
     * @return void
     */
    public function testApplesFilterWithNotFlag(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['not' => ['eq' => 'admin@example.com']],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" != \'admin@example.com\'', $sql);
    }

    /**
     * Test that the 'ci' flag compares the lowercased column with the lowercased value.
     *
     * @return void
     */
    public function testApplesFilterWithCaseInsensitiveFlag(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['ci' => ['eq' => 'TEST@EXAMPLE.COM']],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("users"."email") = LOWER(\'TEST@EXAMPLE.COM\')', $sql);
    }

    /**
     * Test that the 'ci' and 'not' flags combine into a negated comparison, which ignores the case.
     *
     * @return void
     */
    public function testApplesFilterWithMultipleFlags(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['ci' => ['not' => ['like' => '%@EXAMPLE.COM']]],
            ],
        ]);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("users"."email") NOT LIKE LOWER(\'%@EXAMPLE.COM\')', $sql);
    }

    /**
     * Test that a filter, which is allowed with its class, is accepted and the query is built.
     *
     * @return void
     */
    public function testUsesCustomFilterClass(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = [
                'custom_field' => DefaultFilter::class,
            ];
        };
        $request = Request::create('/users', 'GET', [
            'filter' => [
                'custom_field' => ['eq' => 'value'],
            ],
        ]);

        $query = $repository->builder($request);

        // The custom filter should be applied
        $this->assertInstanceOf(BaseQuery::class, $query);
    }

    /**
     * Test that a filter, which is allowed with a column, is applied to that column instead of its name.
     *
     * @return void
     */
    public function testApplesFilterWithCustomColumn(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = [
                'org_id' => [DefaultFilter::class, 'organization_id'],
            ];
        };
        $request = $this->createRequestWithUser([
            'filter' => [
                'org_id' => ['eq' => 5],
            ],
        ]);

        $query = $repository->builder($request);

        $sql = $query->toRawSql();
        // Should use organization_id column instead of org_id
        $this->assertStringContainsString('"organization_id" = 5', $sql);
    }

    /**
     * Test that a filter, which is not listed, is refused, when not all filters are allowed.
     *
     * @return void
     */
    public function testThrowsExceptionForDisallowedFilter(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('filter is not allowed');

        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected bool $allowAllFilters = false;

            protected array $allowedFilters = ['id', 'email', 'name'];
        };
        $request = $this->createRequestWithUser([
            'filter' => [
                'password' => ['eq' => 'secret'],
            ],
        ]);

        $repository->builder($request);
    }

    /**
     * Test that the builder returns a base query, when it is not given a request.
     *
     * @return void
     */
    public function testBuilderWithoutRequestReturnsBaseQuery(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;
        };

        $query = $repo->builder();

        $this->assertInstanceOf(BaseQuery::class, $query);
    }

    /**
     * Test that deleting a model, which uses soft deletes, only soft deletes it.
     *
     * @return void
     */
    public function testDeleteSoftDeletesSoftDeletableModel(): void
    {
        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = Organization::class;
        };

        $organization = Organization::query()->create(['slug' => 'acme', 'name' => 'Acme']);

        $this->assertTrue($repo->isSoftDeletable());
        $this->assertTrue($repo->delete($organization));
        $this->assertSoftDeleted($organization);
    }

    /**
     * Test that force deleting a model, which uses soft deletes, deletes it first and then removes it from the
     * database.
     *
     * @return void
     */
    public function testForceDeleteDeletesFirstAndThenRemovesSoftDeletableModel(): void
    {
        $repo = new class extends CrudRepository
        {
            public int $deletes = 0;

            protected BaseModel|string $model = Organization::class;

            public function delete(BaseModel $model): bool
            {
                $this->deletes++;

                return parent::delete($model);
            }
        };

        $organization = Organization::query()->create(['slug' => 'acme', 'name' => 'Acme']);

        $this->assertTrue($repo->forceDelete($organization));
        $this->assertSame(1, $repo->deletes);
        $this->assertModelMissing($organization);
    }

    /**
     * Test that force deleting a model, which does not use soft deletes, deletes it only once.
     *
     * @return void
     */
    public function testForceDeleteDeletesModelWithoutSoftDeletesOnce(): void
    {
        $model = get_class(new class extends BaseModel
        {
            protected $table = 'organizations';

            protected $fillable = ['slug', 'name'];
        });

        $repo = new class($model) extends CrudRepository
        {
            public int $deletes = 0;

            public function __construct(string $model)
            {
                $this->model = $model;
            }

            public function delete(BaseModel $model): bool
            {
                $this->deletes++;

                return parent::delete($model);
            }
        };

        $organization = $model::query()->create(['slug' => 'acme', 'name' => 'Acme']);

        $this->assertFalse($repo->isSoftDeletable());
        $this->assertTrue($repo->forceDelete($organization));
        $this->assertSame(1, $repo->deletes);
        $this->assertModelMissing($organization);
    }

    /**
     * Test that force deleting stops and keeps the model, when deleting it fails.
     *
     * @return void
     */
    public function testForceDeleteStopsWhenDeleteFails(): void
    {
        $repo = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            public function delete(BaseModel $model): bool
            {
                return false;
            }
        };

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertFalse($repo->forceDelete($user));
        $this->assertModelExists($user);
    }
}
