<?php

namespace Core\Tests\Unit\Http\Filters;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\DateFilter;
use Core\Models\User;
use Core\Queries\BaseQuery;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

/**
 * Class DateFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters
 */
class DateFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Apply a date filter on `created_at` and get the query's SQL.
     *
     * @param FilterOperation $operation
     * @param mixed $value
     * @param FilterFlag[] $flags
     * @param string|null $timezone
     * @param BaseQuery|null $query
     * @param string $column
     *
     * @return string
     */
    private function sql(
        FilterOperation $operation,
        mixed $value,
        array $flags = [],
        ?string $timezone = null,
        ?BaseQuery $query = null,
        string $column = 'created_at'
    ): string {
        $query ??= User::query();

        (new DateFilter('users'))
            ->setOperation($operation)
            ->setFlags($flags)
            ->setTimezone($timezone)
            ->apply($query, $column, $value);

        return $query->toRawSql();
    }

    /**
     * Get a query of users, whose `email_verified_at` has a `date` cast.
     *
     * @return BaseQuery
     */
    private function queryWithDateColumn(): BaseQuery
    {
        $model = new class extends User
        {
            protected $table = 'users';

            protected $casts = ['email_verified_at' => 'date'];
        };

        return $model::query();
    }

    /**
     * Test that `eq` on a date matches the whole day, from its start up to the start of the next day.
     *
     * @return void
     */
    public function testEqOnADateIsTheWholeDay(): void
    {
        $this->assertStringContainsString(
            '("users"."created_at" >= \'2026-06-30 00:00:00\' and "users"."created_at" < \'2026-07-01 00:00:00\')',
            $this->sql(FilterOperation::EQ, '2026-06-30')
        );
    }

    /**
     * Test that `eq` with the `not` flag on a date matches the moments before or after the whole day.
     *
     * @return void
     */
    public function testNotEqOnADateIsOutsideOfTheDay(): void
    {
        $this->assertStringContainsString(
            '("users"."created_at" < \'2026-06-30 00:00:00\' or "users"."created_at" >= \'2026-07-01 00:00:00\')',
            $this->sql(FilterOperation::EQ, '2026-06-30', [FilterFlag::Not])
        );
    }

    /**
     * Test that the comparisons on a date treat it as the whole day, so `gt` starts after it and `lte` includes all of
     * it, with or without the `not` flag.
     *
     * @return void
     */
    public function testComparisonsOnADateCoverTheWholeDay(): void
    {
        $cases = [
            [FilterOperation::GT, [], '>= \'2026-07-01 00:00:00\''],
            [FilterOperation::GTE, [], '>= \'2026-06-30 00:00:00\''],
            [FilterOperation::LT, [], '< \'2026-06-30 00:00:00\''],
            [FilterOperation::LTE, [], '< \'2026-07-01 00:00:00\''],
            [FilterOperation::GT, [FilterFlag::Not], '< \'2026-07-01 00:00:00\''],
            [FilterOperation::LTE, [FilterFlag::Not], '>= \'2026-07-01 00:00:00\''],
        ];

        foreach ($cases as [$operation, $flags, $expected]) {
            $this->assertStringContainsString(
                '"users"."created_at" ' . $expected,
                $this->sql($operation, '2026-06-30', $flags),
                $operation->value . (empty($flags) ? '' : ' with not')
            );
        }
    }

    /**
     * Test that a date and time, with or without seconds and the `T` separator, is compared as the exact moment.
     *
     * @return void
     */
    public function testDateAndTimeIsComparedExactly(): void
    {
        $this->assertStringContainsString(
            '"users"."created_at" = \'2026-06-30 12:30:00\'',
            $this->sql(FilterOperation::EQ, '2026-06-30 12:30')
        );
        $this->assertStringContainsString(
            '"users"."created_at" <= \'2026-06-30 12:30:45\'',
            $this->sql(FilterOperation::LTE, '2026-06-30T12:30:45')
        );
        $this->assertStringContainsString(
            '"users"."created_at" > \'2026-06-30 12:30:45\'',
            $this->sql(FilterOperation::GT, '2026-06-30T12:30:45')
        );
    }

    /**
     * Test that a value with an offset, `Z` included, is converted to the app's timezone.
     *
     * @return void
     */
    public function testOffsetIsConvertedToTheAppTimezone(): void
    {
        $cases = [
            '2026-06-30T12:00:00Z' => '2026-06-30 12:00:00',
            '2026-06-30T12:00:00+02:00' => '2026-06-30 10:00:00',
            '2026-06-30T12:00:00+0530' => '2026-06-30 06:30:00',
            '2026-06-30T12:00:00-05:00' => '2026-06-30 17:00:00',
        ];

        foreach ($cases as $value => $expected) {
            $this->assertStringContainsString(
                "\"users\".\"created_at\" > '$expected'",
                $this->sql(FilterOperation::GT, $value),
                $value
            );
        }
    }

    /**
     * Test that the values without an offset are read in the user's timezone, while the ones with it keep their offset.
     *
     * @return void
     */
    public function testValuesWithoutAnOffsetAreReadInTheUserTimezone(): void
    {
        $this->assertStringContainsString(
            '("users"."created_at" >= \'2026-06-29 21:00:00\' and "users"."created_at" < \'2026-06-30 21:00:00\')',
            $this->sql(FilterOperation::EQ, '2026-06-30', [], 'Europe/Kyiv')
        );
        $this->assertStringContainsString(
            '"users"."created_at" <= \'2026-06-30 09:00:00\'',
            $this->sql(FilterOperation::LTE, '2026-06-30 12:00', [], 'Europe/Kyiv')
        );
        $this->assertStringContainsString(
            '"users"."created_at" <= \'2026-06-30 12:00:00\'',
            $this->sql(FilterOperation::LTE, '2026-06-30T12:00:00Z', [], 'Europe/Kyiv')
        );
    }

    /**
     * Test that a timezone, which is not valid or is missing, falls back to UTC, while a valid one is kept.
     *
     * @return void
     */
    public function testTimezoneThatIsNotValidFallsBackToUtc(): void
    {
        $this->assertSame('UTC', (new DateFilter)->setTimezone('Mars/Olympus')->getTimezone());
        $this->assertSame('UTC', (new DateFilter)->setTimezone(null)->getTimezone());
        $this->assertSame('Europe/Kyiv', (new DateFilter)->setTimezone('Europe/Kyiv')->getTimezone());
    }

    /**
     * Test that `in` matches any of the dates and moments, which are given as a comma-separated string or a list.
     *
     * @return void
     */
    public function testInMatchesAnyOfTheValues(): void
    {
        foreach (['2026-06-30,2026-07-02 08:00', ['2026-06-30', '2026-07-02 08:00']] as $value) {
            $sql = $this->sql(FilterOperation::IN, $value);

            $this->assertStringContainsString('"users"."created_at" >= \'2026-06-30 00:00:00\'', $sql);
            $this->assertStringContainsString('or "users"."created_at" = \'2026-07-02 08:00:00\'', $sql);
        }
    }

    /**
     * Test that the values, which are not a date, or a date and time, such as words, impossible dates, out of range
     * offsets or other formats, throw.
     *
     * @return void
     */
    public function testValuesThatAreNotADateThrow(): void
    {
        $values = [
            'yesterday', '2026-02-30', '2026-13-01', '2026-06-30 24:00', '2026-06-30 12:60',
            '2026-06-30T12:00:00+15:00', '30/06/2026', '2026-06-30Z', 123, '',
        ];

        foreach ($values as $value) {
            try {
                $this->sql(FilterOperation::LTE, $value);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringStartsWith(
                    'A date filter takes a date, or a date and time',
                    $exception->getMessage()
                );

                continue;
            }

            $this->fail('No exception for ' . var_export($value, true));
        }
    }

    /**
     * Test that the `like` operation and the `ci` flag, which date filters don't support, throw.
     *
     * @return void
     */
    public function testUnsupportedOperationAndFlagThrow(): void
    {
        try {
            $this->sql(FilterOperation::LIKE, '2026%');
            $this->fail('No exception for the like operation');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame("The 'like' operation is not supported by date filters.", $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'ci' flag is not supported by date filters.");

        $this->sql(FilterOperation::EQ, '2026-06-30', [FilterFlag::CaseInsensitive]);
    }

    /**
     * Test that the `null` operation is left to the null filter, so `false` asks for the column not to be null.
     *
     * @return void
     */
    public function testNullOperationIsLeftToTheNullFilter(): void
    {
        $this->assertStringContainsString(
            '"users"."created_at" is not null',
            $this->sql(FilterOperation::NULL, 'false')
        );
    }

    /**
     * Test that a column with a `date` cast compares the dates as given, without the whole day's range or the user's
     * timezone.
     *
     * @return void
     */
    public function testDateColumnComparesDatesAsGiven(): void
    {
        $equals = $this->sql(
            FilterOperation::EQ,
            '2026-06-30',
            [],
            'America/New_York',
            $this->queryWithDateColumn(),
            'email_verified_at'
        );
        $upTo = $this->sql(
            FilterOperation::LTE,
            '2026-06-30',
            [],
            null,
            $this->queryWithDateColumn(),
            'email_verified_at'
        );

        $this->assertStringContainsString('"users"."email_verified_at" = \'2026-06-30\'', $equals);
        $this->assertStringContainsString('"users"."email_verified_at" <= \'2026-06-30\'', $upTo);
    }

    /**
     * Test that a column with a `date` cast refuses a value, which has a time.
     *
     * @return void
     */
    public function testDateColumnRefusesATime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A date filter takes a date without time, such as 2026-06-30.');

        $query = $this->queryWithDateColumn();

        $this->sql(FilterOperation::EQ, '2026-06-30 12:00', [], null, $query, 'email_verified_at');
    }

    /**
     * Test that the whole day matches the users, who were created within that day in the user's timezone, but not the
     * ones just outside of it.
     *
     * @return void
     */
    public function testWholeDayIsTheDayInTheUserTimezone(): void
    {
        $ids = [];

        $moments = ['2026-06-29 20:59:59', '2026-06-29 21:00:00', '2026-06-30 20:59:59', '2026-06-30 21:00:00'];

        foreach ($moments as $i => $at) {
            $ids[] = User::query()->forceCreate([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => 'password123',
                'created_at' => $at,
            ])->id;
        }

        $query = User::query();

        (new DateFilter('users'))
            ->setOperation(FilterOperation::EQ)
            ->setTimezone('Europe/Kyiv')
            ->apply($query, 'created_at', '2026-06-30');

        $this->assertEquals([$ids[1], $ids[2]], $query->orderBy('id')->pluck('id')->all());
    }
}
