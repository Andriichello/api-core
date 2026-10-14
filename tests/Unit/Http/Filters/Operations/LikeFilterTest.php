<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\LikeFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class LikeFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class LikeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function testApplesLikeFilter(): void
    {
        $query = User::query();
        $filter = new LikeFilter;
        $filter->setOperation(FilterOperation::LIKE);
        $filter->setFlags([]);

        $filter->apply($query, 'email', '%@example.com');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" LIKE \'%@example.com\'', $sql);
    }

    public function testApplesNotLikeFilter(): void
    {
        $query = User::query();
        $filter = new LikeFilter;
        $filter->setOperation(FilterOperation::LIKE);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'email', '%@example.com');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" NOT LIKE \'%@example.com\'', $sql);
    }

    public function testAppliesCaseInsensitiveLikeFilter(): void
    {
        $query = User::query();
        $filter = new LikeFilter;
        $filter->setOperation(FilterOperation::LIKE);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'email', '%@EXAMPLE.COM');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("email") LIKE LOWER(\'%@EXAMPLE.COM\')', $sql);
    }

    public function testAppliesCaseInsensitiveNotLikeFilter(): void
    {
        $query = User::query();
        $filter = new LikeFilter;
        $filter->setOperation(FilterOperation::LIKE);
        $filter->setFlags([FilterFlag::CaseInsensitive, FilterFlag::Not]);

        $filter->apply($query, 'email', '%@EXAMPLE.COM');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("email") NOT LIKE LOWER(\'%@EXAMPLE.COM\')', $sql);
    }

    public function testQualifiesTheColumnWithTheTable(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'email', '%@example.com');

        $this->assertStringContainsString('"users"."email" LIKE \'%@example.com\'', $query->toRawSql());
    }

    public function testKeepsAColumnThatNamesItsTable(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'organizations.name', 'Acme%');

        $this->assertStringContainsString('"organizations"."name" LIKE \'Acme%\'', $query->toRawSql());
    }

    public function testListOfValuesMatchesAnyOfThem(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'email', ['a%', 'b%']);

        $this->assertStringContainsString(
            '("users"."email" LIKE \'a%\' or "users"."email" LIKE \'b%\')',
            $query->toRawSql()
        );
    }

    public function testListOfValuesWithNotMatchesNoneOfThem(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->setFlags([FilterFlag::Not])->apply($query, 'email', ['a%', 'b%']);

        $this->assertStringContainsString(
            '("users"."email" NOT LIKE \'a%\' and "users"."email" NOT LIKE \'b%\')',
            $query->toRawSql()
        );
    }
}
