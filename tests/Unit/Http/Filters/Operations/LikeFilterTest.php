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

    /**
     * Test that the `like` filter matches the column against the pattern.
     *
     * @return void
     */
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

    /**
     * Test that the `like` filter with the `not` flag asks for the column not to match the pattern.
     *
     * @return void
     */
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

    /**
     * Test that the `like` filter with the `ci` flag matches the lowercased column against the lowercased pattern.
     *
     * @return void
     */
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

    /**
     * Test that the `like` filter with the `ci` and `not` flags asks for the lowercased column not to match the
     * lowercased pattern.
     *
     * @return void
     */
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

    /**
     * Test that the column is qualified with the table, which the filter is given.
     *
     * @return void
     */
    public function testQualifiesTheColumnWithTheTable(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'email', '%@example.com');

        $this->assertStringContainsString('"users"."email" LIKE \'%@example.com\'', $query->toRawSql());
    }

    /**
     * Test that a column, which already names its table, is kept as it is.
     *
     * @return void
     */
    public function testKeepsAColumnThatNamesItsTable(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'organizations.name', 'Acme%');

        $this->assertStringContainsString('"organizations"."name" LIKE \'Acme%\'', $query->toRawSql());
    }

    /**
     * Test that a list of patterns matches the rows, which match any of them.
     *
     * @return void
     */
    public function testListOfValuesMatchesAnyOfThem(): void
    {
        $query = User::query();

        (new LikeFilter('users'))->apply($query, 'email', ['a%', 'b%']);

        $this->assertStringContainsString(
            '("users"."email" LIKE \'a%\' or "users"."email" LIKE \'b%\')',
            $query->toRawSql()
        );
    }

    /**
     * Test that a list of patterns with the `not` flag matches the rows, which match none of them.
     *
     * @return void
     */
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
