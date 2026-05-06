<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\NullFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class NullFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class NullFilterTest extends TestCase
{
    use RefreshDatabase;

    public function testApplesNullFilter(): void
    {
        $query = User::query();
        $filter = new NullFilter;
        $filter->setOperation(FilterOperation::NULL);
        $filter->setFlags([]);

        $filter->apply($query, 'email_verified_at', null);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email_verified_at" is null', $sql);
    }

    public function testApplesNotNullFilter(): void
    {
        $query = User::query();
        $filter = new NullFilter;
        $filter->setOperation(FilterOperation::NULL);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'email_verified_at', null);

        $sql = $query->toRawSql();
        $this->assertStringContainsString('"email_verified_at" is not null', $sql);
    }
}
