<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\EqFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class EqFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class EqFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the `eq` filter asks for the column to be equal to the value.
     *
     * @return void
     */
    public function testApplesEqualsFilter(): void
    {
        $query = User::query();
        $filter = new EqFilter;
        $filter->setOperation(FilterOperation::EQ);
        $filter->setFlags([]);

        $filter->apply($query, 'email', 'test@example.com');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" = \'test@example.com\'', $sql);
    }

    /**
     * Test that the `eq` filter with the `not` flag asks for the column not to be equal to the value.
     *
     * @return void
     */
    public function testApplesNotEqualsFilter(): void
    {
        $query = User::query();
        $filter = new EqFilter;
        $filter->setOperation(FilterOperation::EQ);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'email', 'test@example.com');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email" != \'test@example.com\'', $sql);
    }

    /**
     * Test that the `eq` filter with the `ci` flag compares the lowercased column and value.
     *
     * @return void
     */
    public function testAppliesCaseInsensitiveFilter(): void
    {
        $query = User::query();
        $filter = new EqFilter;
        $filter->setOperation(FilterOperation::EQ);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'email', 'TEST@EXAMPLE.COM');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("email") = LOWER(\'TEST@EXAMPLE.COM\')', $sql);
    }

    /**
     * Test that the `eq` filter with the `ci` and `not` flags asks for the lowercased column and value not to be equal.
     *
     * @return void
     */
    public function testAppliesCaseInsensitiveNotEqualsFilter(): void
    {
        $query = User::query();
        $filter = new EqFilter;
        $filter->setOperation(FilterOperation::EQ);
        $filter->setFlags([FilterFlag::CaseInsensitive, FilterFlag::Not]);

        $filter->apply($query, 'email', 'TEST@EXAMPLE.COM');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER("email") != LOWER(\'TEST@EXAMPLE.COM\')', $sql);
    }
}
