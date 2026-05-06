<?php

namespace Core\Tests\Unit\Repositories;

use Core\Http\Filters\DefaultFilter;
use Core\Models\BaseModel;
use Core\Models\User;
use Core\Queries\BaseQuery;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/**
 * Class CrudRepositoryFiltersTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Repositories
 */
class CrudRepositoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    private CrudRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = ['email_verified_at'];
        };
    }

    private function createRequestWithUser(array $data = []): Request
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $request = Request::create('/users', 'GET', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function testAppliesSingleFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['eq' => 'test@example.com'],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" = \'test@example.com\'', $sql);
    }

    public function testAppliesMultipleFilters(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['like' => '%@example.com'],
                'name' => ['eq' => 'John Doe'],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" LIKE \'%@example.com\'', $sql);
        $this->assertStringContainsString('"name" = \'John Doe\'', $sql);
    }

    public function testApplesInFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'id' => ['in' => [1, 2, 3]],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" in (1, 2, 3)', $sql);
    }

    public function testApplesNullFilter(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email_verified_at' => ['null' => true],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email_verified_at" is null', $sql);
    }

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

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" > 5', $sql);
        $this->assertStringContainsString('"id" < 10', $sql);
    }

    public function testApplesFilterWithNotFlag(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['not' => ['eq' => 'admin@example.com']],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" != \'admin@example.com\'', $sql);
    }

    public function testApplesFilterWithCaseInsensitiveFlag(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['ci' => ['eq' => 'TEST@EXAMPLE.COM']],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER(email) = LOWER(\'TEST@EXAMPLE.COM\')', $sql);
    }

    public function testApplesFilterWithMultipleFlags(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['ci' => ['not' => ['like' => '%@EXAMPLE.COM']]],
            ],
        ]);

        $query = $this->repository->builder($request);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER(email) NOT LIKE LOWER(\'%@EXAMPLE.COM\')', $sql);
    }

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

    public function testBuilderWithoutRequestReturnsBaseQuery(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $query = $this->repository->builder();

        $this->assertInstanceOf(BaseQuery::class, $query);
    }
}
