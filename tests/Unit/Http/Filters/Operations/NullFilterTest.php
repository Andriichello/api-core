<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\NullFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

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

    public function testEmptyOrTrueValueAsksForNull(): void
    {
        foreach (['', true, 1, '1', 'true', 'yes', 'on'] as $value) {
            $query = User::query();

            (new NullFilter('users'))->apply($query, 'email_verified_at', $value);

            $this->assertStringContainsString(
                '"users"."email_verified_at" is null',
                $query->toRawSql(),
                'value: ' . var_export($value, true)
            );
        }
    }

    public function testFalseValueAsksForNotNull(): void
    {
        foreach ([false, 0, '0', 'false', 'no', 'off'] as $value) {
            $query = User::query();

            (new NullFilter('users'))->apply($query, 'email_verified_at', $value);

            $this->assertStringContainsString(
                '"users"."email_verified_at" is not null',
                $query->toRawSql(),
                'value: ' . var_export($value, true)
            );
        }
    }

    public function testNotInvertsTheValue(): void
    {
        $query = User::query();

        (new NullFilter('users'))
            ->setFlags([FilterFlag::Not])
            ->apply($query, 'email_verified_at', 'false');

        $this->assertStringContainsString('"users"."email_verified_at" is null', $query->toRawSql());
    }

    public function testValueThatIsNotABooleanThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'null' operation takes true or false, such as [null]=false.");

        (new NullFilter('users'))->apply(User::query(), 'email_verified_at', 'maybe');
    }
}
