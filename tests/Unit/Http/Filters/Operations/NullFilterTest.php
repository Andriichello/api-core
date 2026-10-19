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

    /**
     * Test that the `null` filter asks for the column to be null.
     *
     * @return void
     */
    public function testApplesNullFilter(): void
    {
        $query = User::query();
        $filter = new NullFilter;
        $filter->setOperation(FilterOperation::NULL);
        $filter->setFlags([]);

        $filter->apply($query, 'email_verified_at', null);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('"email_verified_at" is null', $sql);
    }

    /**
     * Test that the `null` filter with the `not` flag asks for the column not to be null.
     *
     * @return void
     */
    public function testApplesNotNullFilter(): void
    {
        $query = User::query();
        $filter = new NullFilter;
        $filter->setOperation(FilterOperation::NULL);
        $filter->setFlags([FilterFlag::Not]);

        $filter->apply($query, 'email_verified_at', null);

        $sql = $this->rawSql($query);
        $this->assertStringContainsString('"email_verified_at" is not null', $sql);
    }

    /**
     * Test that an empty value, or one, which means true, asks for the column to be null.
     *
     * @return void
     */
    public function testEmptyOrTrueValueAsksForNull(): void
    {
        foreach (['', true, 1, '1', 'true', 'yes', 'on'] as $value) {
            $query = User::query();

            (new NullFilter('users'))->apply($query, 'email_verified_at', $value);

            $this->assertStringContainsString(
                '"users"."email_verified_at" is null',
                $this->rawSql($query),
                'value: ' . var_export($value, true)
            );
        }
    }

    /**
     * Test that a value, which means false, asks for the column not to be null.
     *
     * @return void
     */
    public function testFalseValueAsksForNotNull(): void
    {
        foreach ([false, 0, '0', 'false', 'no', 'off'] as $value) {
            $query = User::query();

            (new NullFilter('users'))->apply($query, 'email_verified_at', $value);

            $this->assertStringContainsString(
                '"users"."email_verified_at" is not null',
                $this->rawSql($query),
                'value: ' . var_export($value, true)
            );
        }
    }

    /**
     * Test that the `not` flag inverts the value, so `false` asks for the column to be null.
     *
     * @return void
     */
    public function testNotInvertsTheValue(): void
    {
        $query = User::query();

        (new NullFilter('users'))
            ->setFlags([FilterFlag::Not])
            ->apply($query, 'email_verified_at', 'false');

        $this->assertStringContainsString('"users"."email_verified_at" is null', $this->rawSql($query));
    }

    /**
     * Test that a value, which is not true or false, throws.
     *
     * @return void
     */
    public function testValueThatIsNotABooleanThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'null' operation takes true or false, such as [null]=false.");

        (new NullFilter('users'))->apply(User::query(), 'email_verified_at', 'maybe');
    }
}
