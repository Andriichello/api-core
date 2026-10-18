<?php

namespace Core\Tests\Unit\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Http\Filters\Operations\ContainsFilter;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class ContainsFilterTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Filters\Operations
 */
class ContainsFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the value is wrapped in `%` wildcards, while its own `%`, `_` and `!` are escaped.
     *
     * @return void
     */
    public function testWrapsTheValueAndEscapesItsWildcards(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))->apply($query, 'name', '50%_off!');

        $this->assertStringContainsString(
            '"users"."name" LIKE \'%50!%!_off!!%\' ESCAPE \'!\'',
            $query->toRawSql()
        );
    }

    /**
     * Test that the `ci` flag compares the lowercased column and value.
     *
     * @return void
     */
    public function testComparesCaseInsensitively(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))
            ->setFlags([FilterFlag::CaseInsensitive])
            ->apply($query, 'name', 'John');

        $this->assertStringContainsString(
            'LOWER("users"."name") LIKE LOWER(\'%John%\') ESCAPE \'!\'',
            $query->toRawSql()
        );
    }

    /**
     * Test that the `not` flag excludes the rows, which contain the value.
     *
     * @return void
     */
    public function testNotExcludesTheValue(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))
            ->setFlags([FilterFlag::Not])
            ->apply($query, 'name', 'John');

        $this->assertStringContainsString('"users"."name" NOT LIKE \'%John%\' ESCAPE \'!\'', $query->toRawSql());
    }

    /**
     * Test that a list of values matches the rows, which contain any of them.
     *
     * @return void
     */
    public function testListOfValuesMatchesAnyOfThem(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))->apply($query, 'name', ['John', 'Jane']);

        $this->assertStringContainsString(
            '("users"."name" LIKE \'%John%\' ESCAPE \'!\' or "users"."name" LIKE \'%Jane%\' ESCAPE \'!\')',
            $query->toRawSql()
        );
    }

    /**
     * Test that the `%` and `_` of the value are matched literally, instead of as wildcards.
     *
     * @return void
     */
    public function testMatchesTheValueLiterally(): void
    {
        foreach (['50% off', '500 off', 'a_b', 'axb'] as $i => $name) {
            User::query()->create([
                'name' => $name,
                'email' => "user$i@example.com",
                'password' => 'password123',
            ]);
        }

        foreach (['50%' => ['50% off'], '_' => ['a_b']] as $value => $expected) {
            $query = User::query();
            (new ContainsFilter('users'))->apply($query, 'name', $value);

            $this->assertEquals($expected, $query->pluck('name')->all(), "contains $value");
        }
    }
}
