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
 */
class InFilterTest extends TestCase
{
    use RefreshDatabase;

    public function testAppliesInFilterWithArray(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([]);

        $filter->apply($query, 'id', [1, 2, 3]);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" in (1, 2, 3)', $sql);
    }

    public function testAppliesInFilterWithString(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([]);

        $filter->apply($query, 'id', '1,2,3');

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" in (\'1\', \'2\', \'3\')', $sql);
    }

    public function testAppliesNotIn(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'id', [1, 2, 3]);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"id" not in (1, 2, 3)', $sql);
    }

    public function testAppliesCaseInsensitive(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::CaseInsensitive]);

        $filter->apply($query, 'email', ['TEST@EXAMPLE.COM', 'ADMIN@EXAMPLE.COM']);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER(email) IN (LOWER(\'TEST@EXAMPLE.COM\'), LOWER(\'ADMIN@EXAMPLE.COM\'))', $sql);
    }

    public function testAppliesCaseInsensitiveWithNot(): void
    {
        $query = User::query();
        $filter = new InFilter;
        $filter->setOperation(FilterOperation::IN);
        $filter->setFlags([FilterFlag::CaseInsensitive, FilterFlag::Not]);

        $filter->apply($query, 'email', ['TEST@EXAMPLE.COM']);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('LOWER(email) NOT IN (LOWER(\'TEST@EXAMPLE.COM\'))', $sql);
    }
}
