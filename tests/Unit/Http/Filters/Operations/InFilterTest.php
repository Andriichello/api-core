<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\InFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class InFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class InFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the `in` filter asks for the column to be one of the values in a list.
     *
     * @return void
     */
    public function testAppliesInFilterWithArray(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([]);

        $filter->apply($query, 'id', [1, 2, 3]);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('"id" in (1, 2, 3)', $sql);
    }

    /**
     * Test that the `in` filter splits a comma-separated string into the values.
     *
     * @return void
     */
    public function testAppliesInFilterWithString(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([]);

        $filter->apply($query, 'id', '1,2,3');

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('"id" in (\'1\', \'2\', \'3\')', $sql);
    }

    /**
     * Test that the `in` filter with the `not` flag asks for the column to be none of the values.
     *
     * @return void
     */
    public function testAppliesNotIn(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', [1, 2, 3]);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('"id" not in (1, 2, 3)', $sql);
    }

    /**
     * Test that the `in` filter with the `ci` flag compares the lowercased column and values.
     *
     * @return void
     */
    public function testAppliesCaseInsensitive(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'email', ['TEST@EXAMPLE.COM', 'ADMIN@EXAMPLE.COM']);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString(
            'LOWER("email") IN (LOWER(\'TEST@EXAMPLE.COM\'), LOWER(\'ADMIN@EXAMPLE.COM\'))',
            $sql
        );
    }

    /**
     * Test that the `in` filter with the `ci` and `not` flags asks for the lowercased column to be none of the
     * lowercased values.
     *
     * @return void
     */
    public function testAppliesCaseInsensitiveWithNot(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::CaseInsensitive, FilterFlag::Not]);

        $filter->apply($query, 'email', ['TEST@EXAMPLE.COM']);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('LOWER("email") NOT IN (LOWER(\'TEST@EXAMPLE.COM\'))', $sql);
    }
}
