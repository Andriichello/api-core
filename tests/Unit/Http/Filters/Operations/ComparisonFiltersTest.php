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
