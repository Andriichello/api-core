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
