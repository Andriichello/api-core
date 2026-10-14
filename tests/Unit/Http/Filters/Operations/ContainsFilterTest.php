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

    public function testWrapsTheValueAndEscapesItsWildcards(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))->apply($query, 'name', '50%_off!');

        $this->assertStringContainsString(
            '"users"."name" LIKE \'%50!%!_off!!%\' ESCAPE \'!\'',
            $query->toRawSql()
        );
    }

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

    public function testNotExcludesTheValue(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))
            ->setFlags([FilterFlag::Not])
            ->apply($query, 'name', 'John');

        $this->assertStringContainsString('"users"."name" NOT LIKE \'%John%\' ESCAPE \'!\'', $query->toRawSql());
    }

    public function testListOfValuesMatchesAnyOfThem(): void
    {
        $query = User::query();

        (new ContainsFilter('users'))->apply($query, 'name', ['John', 'Jane']);

        $this->assertStringContainsString(
            '("users"."name" LIKE \'%John%\' ESCAPE \'!\' or "users"."name" LIKE \'%Jane%\' ESCAPE \'!\')',
            $query->toRawSql()
        );
    }

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
