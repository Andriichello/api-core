<?php

namespace Core\Tests\Unit\Repositories;

use Core\Enums\FilterOperation;
use Core\Http\Filters\DateFilter;
use Core\Http\Filters\DefaultFilter;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Queries\BaseQuery;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $request = Request::create('/users', 'GET', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function errorsOf(Request $request, ?CrudRepository $repository = null): array
    {
        try {
            ($repository ?? $this->repository)->builder($request);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('The filters did not answer with 422.');
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
        $this->assertStringContainsString('LOWER("users"."email") = LOWER(\'TEST@EXAMPLE.COM\')', $sql);
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
        $this->assertStringContainsString('LOWER("users"."email") NOT LIKE LOWER(\'%@EXAMPLE.COM\')', $sql);
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

    public function testThrowsExceptionForUnknownFilterWhenAllFiltersAreAllowed(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('\'unknown_column\' filter is not allowed');

        $request = $this->createRequestWithUser([
            'filter' => [
                'unknown_column' => ['eq' => 'value'],
            ],
        ]);

        $this->repository->builder($request);
    }

    public function testRequestedFiltersKeepEveryEntryOfTheSameField(): void
    {
        $request = Request::create('/users', 'GET', [
            'filter' => [
                'id' => [
                    'gt' => 5,
                    'lt' => 10,
                ],
                'email' => ['eq' => 'test@example.com'],
            ],
        ]);

        $filters = $this->repository->getRequestedFilters($request);

        $this->assertCount(3, $filters);
        $this->assertSame(['id', 'id', 'email'], array_column($filters, 'field'));
        $this->assertSame(
            [FilterOperation::GT, FilterOperation::LT, FilterOperation::EQ],
            array_column($filters, 'operation')
        );
        $this->assertSame([5, 10, 'test@example.com'], array_column($filters, 'value'));
    }

    public function testFiltersNarrowDownTheResults(): void
    {
        $organization = Organization::query()->create([
            'slug' => 'acme',
            'name' => 'Acme',
        ]);

        $user = User::query()->forceCreate([
            'organization_id' => $organization->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $colleague = User::query()->forceCreate([
            'organization_id' => $organization->id,
            'name' => 'Colleague',
            'email' => 'colleague@example.com',
            'password' => 'password123',
        ]);

        $request = Request::create('/users', 'GET', [
            'filter' => [
                'email' => ['eq' => 'colleague@example.com'],
            ],
        ]);
        $request->setUserResolver(fn () => $user);

        $ids = $this->repository->builder($request)->pluck('users.id')->all();

        $this->assertEquals([$colleague->id], $ids);
    }

    public function testHiddenColumnsAreNotFilterableByDefault(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'password' => ['like' => '$2y$%'],
            ],
        ]);

        $errors = $this->errorsOf($request);

        $this->assertArrayHasKey('filter.password', $errors);
    }

    public function testHiddenColumnCanBeAllowedExplicitly(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = ['password'];
        };
        $request = $this->createRequestWithUser([
            'filter' => [
                'password' => ['eq' => 'secret'],
            ],
        ]);

        $sql = $repository->builder($request)->toRawSql();

        $this->assertStringContainsString('"password" = \'secret\'', $sql);
    }

    public function testNotAllowedFilterNamesTheAllowedOnes(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'unknown_column' => ['eq' => 'value'],
            ],
        ]);

        $message = $this->errorsOf($request)['filter.unknown_column'][0];

        $this->assertStringStartsWith("The 'unknown_column' filter is not allowed. Allowed filters: ", $message);
        $this->assertStringContainsString('email', $message);
        $this->assertStringNotContainsString('password', $message);
    }

    public function testFilterParameterThatIsNotAnArrayAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => 'email',
        ]);

        $this->assertArrayHasKey('filter', $this->errorsOf($request));
    }

    public function testEmptyFilterParameterIsIgnored(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => '',
        ]);

        $query = $this->repository->builder($request);

        $this->assertCount(1, $query->getQuery()->wheres);
    }

    public function testNullFilterValueAnswers422(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $request = Request::create('/users', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['filter' => ['email' => null]]));
        $request->setUserResolver(fn () => $user);

        $this->assertEquals(
            ['A filter value must be a string or a list of strings.'],
            $this->errorsOf($request)['filter.email']
        );
    }

    public function testUnknownOperationAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['between' => 'example'],
            ],
        ]);

        $message = $this->errorsOf($request)['filter.email'][0];

        $this->assertStringStartsWith("Unknown filter operation or flag 'between'.", $message);
    }

    public function testOperationBeforeFlagAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['eq' => ['not' => 'test@example.com']],
            ],
        ]);

        $this->assertEquals(
            ["The 'eq' operation must come after the flags, such as [not][eq]."],
            $this->errorsOf($request)['filter.email']
        );
    }

    public function testBuilderWithoutRequestReturnsBaseQuery(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $query = $this->repository->builder();

        $this->assertInstanceOf(BaseQuery::class, $query);
    }

    public function testEmptyFilterValuesAreSkipped(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['eq' => ''],
                'email_verified_at' => ['null' => ''],
            ],
        ]);

        $sql = $this->repository->builder($request)->toRawSql();

        $this->assertStringNotContainsString('"email" =', $sql);
        $this->assertStringContainsString('"users"."email_verified_at" is null', $sql);
    }

    public function testLikeAndContainsTakeAListOfValues(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email' => ['like' => ['a%', 'b%']],
                'name' => ['contains' => ['John', 'Jane']],
            ],
        ]);

        $sql = $this->repository->builder($request)->toRawSql();

        $this->assertStringContainsString('("users"."email" LIKE \'a%\' or "users"."email" LIKE \'b%\')', $sql);
        $this->assertStringContainsString('"users"."name" LIKE \'%Jane%\' ESCAPE \'!\'', $sql);
    }

    public function testListOfValuesForAComparisonAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'id' => ['gt' => [1, 2]],
            ],
        ]);

        $this->assertEquals(
            ["A list of values is only supported by the 'in', 'like' and 'contains' operations."],
            $this->errorsOf($request)['filter.id']
        );
    }

    public function testNullFilterTakesFalseForNotNull(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'email_verified_at' => ['null' => 'false'],
            ],
        ]);

        $sql = $this->repository->builder($request)->toRawSql();

        $this->assertStringContainsString('"users"."email_verified_at" is not null', $sql);
    }

    public function testInvalidValueOrFlagOfNullFilterAnswers422(): void
    {
        $maybe = $this->createRequestWithUser([
            'filter' => [
                'email_verified_at' => ['null' => 'maybe'],
            ],
        ]);

        $this->assertEquals(
            ["The 'null' operation takes true or false, such as [null]=false."],
            $this->errorsOf($maybe)['filter.email_verified_at']
        );

        $caseInsensitive = $this->createRequestWithUser([
            'filter' => [
                'email_verified_at' => ['ci' => ['null' => '1']],
            ],
        ]);

        $this->assertEquals(
            ["The 'ci' flag is not supported by the 'null' operation."],
            $this->errorsOf($caseInsensitive)['filter.email_verified_at']
        );
    }

    public function testDateFilterIsChosenForTimestampsAndDateCasts(): void
    {
        $this->assertSame(DateFilter::class, $this->repository->classOfAllowedFilter('created_at'));
        $this->assertSame(DateFilter::class, $this->repository->classOfAllowedFilter('updated_at'));
        $this->assertSame(DateFilter::class, $this->repository->classOfAllowedFilter('email_verified_at'));
        $this->assertSame(DateFilter::class, $this->repository->classOfAllowedFilter('suspended_at'));
        $this->assertSame(DateFilter::class, $this->repository->classOfAllowedFilter('last_login_at'));
        $this->assertSame(DefaultFilter::class, $this->repository->classOfAllowedFilter('email'));
    }

    public function testFilterClassCanBeAllowedExplicitly(): void
    {
        $repository = new class extends CrudRepository
        {
            protected BaseModel|string $model = User::class;

            protected array $allowedFilters = [
                'last_login_at' => DateFilter::class,
                'created' => [DefaultFilter::class, 'created_at'],
            ];
        };

        $this->assertSame(DateFilter::class, $repository->classOfAllowedFilter('last_login_at'));
        $this->assertSame(DefaultFilter::class, $repository->classOfAllowedFilter('created'));
    }

    public function testInvalidDateAnswers422(): void
    {
        $request = $this->createRequestWithUser([
            'filter' => [
                'created_at' => ['lte' => 'yesterday'],
            ],
        ]);

        $this->assertStringStartsWith(
            'A date filter takes a date, or a date and time',
            $this->errorsOf($request)['filter.created_at'][0]
        );
    }

    public function testDatesAreReadInTheTimezoneOfTheRequestUser(): void
    {
        $user = User::query()->forceCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'timezone' => 'Europe/Kyiv',
        ]);

        $request = Request::create('/users', 'GET', [
            'filter' => [
                'created_at' => ['eq' => '2026-06-30'],
            ],
        ]);
        $request->setUserResolver(fn () => $user);

        $sql = $this->repository->builder($request)->toRawSql();

        $this->assertStringContainsString('"users"."created_at" >= \'2026-06-29 21:00:00\'', $sql);
        $this->assertStringContainsString('"users"."created_at" < \'2026-06-30 21:00:00\'', $sql);
    }
}
