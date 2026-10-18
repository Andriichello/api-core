<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\GteFilter;
use Core\Http\Filters\Operations\GtFilter;
use Core\Http\Filters\Operations\LteFilter;
use Core\Http\Filters\Operations\LtFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class ComparisonFiltersTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class ComparisonFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the `gt` filter asks for the column to be greater than the value.
     *
     * @return void
     */
    public function testApplesGtFilter(): void
    {
        $query = User::query();
        $filter = new GtFilter;
        $filter->setOperation(FilterOperation::GT);
        $filter->setFlags([]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" > 5', $sql);
    }

    /**
     * Test that the `gt` filter with the `not` flag asks for the column to be less than or equal to the value.
     *
     * @return void
     */
    public function testApplesNotGtFilter(): void
    {
        $query = User::query();
        $filter = new GtFilter;
        $filter->setOperation(FilterOperation::GT);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" <= 5', $sql);
    }

    /**
     * Test that the `gte` filter asks for the column to be greater than or equal to the value.
     *
     * @return void
     */
    public function testApplesGteFilter(): void
    {
        $query = User::query();
        $filter = new GteFilter;
        $filter->setOperation(FilterOperation::GTE);
        $filter->setFlags([]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" >= 5', $sql);
    }

    /**
     * Test that the `gte` filter with the `not` flag asks for the column to be less than the value.
     *
     * @return void
     */
    public function testApplesNotGteFilter(): void
    {
        $query = User::query();
        $filter = new GteFilter;
        $filter->setOperation(FilterOperation::GTE);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" < 5', $sql);
    }

    /**
     * Test that the `lt` filter asks for the column to be less than the value.
     *
     * @return void
     */
    public function testApplesLtFilter(): void
    {
        $query = User::query();
        $filter = new LtFilter;
        $filter->setOperation(FilterOperation::LT);
        $filter->setFlags([]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" < 5', $sql);
    }

    /**
     * Test that the `lt` filter with the `not` flag asks for the column to be greater than or equal to the value.
     *
     * @return void
     */
    public function testApplesNotLtFilter(): void
    {
        $query = User::query();
        $filter = new LtFilter;
        $filter->setOperation(FilterOperation::LT);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" >= 5', $sql);
    }

    /**
     * Test that the `lte` filter asks for the column to be less than or equal to the value.
     *
     * @return void
     */
    public function testApplesLteFilter(): void
    {
        $query = User::query();
        $filter = new LteFilter;
        $filter->setOperation(FilterOperation::LTE);
        $filter->setFlags([]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" <= 5', $sql);
    }

    /**
     * Test that the `lte` filter with the `not` flag asks for the column to be greater than the value.
     *
     * @return void
     */
    public function testApplesNotLteFilter(): void
    {
        $query = User::query();
        $filter = new LteFilter;
        $filter->setOperation(FilterOperation::LTE);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', 5);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" > 5', $sql);
    }

    /**
     * Test that the `gt` filter with the `ci` flag compares the lowercased column and value.
     *
     * @return void
     */
    public function testAppliesCaseInsensitiveGtFilter(): void
    {
        $query = User::query();
        $filter = new GtFilter;
        $filter->setOperation(FilterOperation::GT);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'name', 'John');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("name") > LOWER(\'John\')', $sql);
    }

    /**
     * Test that the `lte` filter with the `ci` flag compares the lowercased column and value.
     *
     * @return void
     */
    public function testAppliesCaseInsensitiveLteFilter(): void
    {
        $query = User::query();
        $filter = new LteFilter;
        $filter->setOperation(FilterOperation::LTE);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'name', 'Zebra');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("name") <= LOWER(\'Zebra\')', $sql);
    }

    /**
     * Test that the comparison filters compare the values as given, whether they are a date and time, a date, which
     * does not exist, or a date.
     *
     * @return void
     */
    public function testValuesOtherThanADateAreComparedAsGiven(): void
    {
        $cases = [
            [new LteFilter('users'), '2026-06-30 12:00:00', '<= \'2026-06-30 12:00:00\''],
            [new LteFilter('users'), '2026-02-30', '<= \'2026-02-30\''],
            [new LtFilter('users'), '2026-06-30', '< \'2026-06-30\''],
            [new GteFilter('users'), '2026-06-30', '>= \'2026-06-30\''],
        ];

        foreach ($cases as [$filter, $value, $expected]) {
            $query = User::query();
            $filter->apply($query, 'created_at', $value);

            $this->assertStringContainsString('"users"."created_at" ' . $expected, $query->toRawSql());
        }
    }

    /**
     * Test that `lte` and `gt` compare a date as given, as only the date filter treats it as the whole day.
     *
     * @return void
     */
    public function testDatesAreComparedAsGiven(): void
    {
        $upTo = User::query();
        (new LteFilter('users'))->apply($upTo, 'created_at', '2026-06-30');

        $after = User::query();
        (new GtFilter('users'))->apply($after, 'created_at', '2026-06-30');

        $this->assertStringContainsString('"users"."created_at" <= \'2026-06-30\'', $upTo->toRawSql());
        $this->assertStringContainsString('"users"."created_at" > \'2026-06-30\'', $after->toRawSql());
    }
}
