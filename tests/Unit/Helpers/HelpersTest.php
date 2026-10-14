<?php

namespace Core\Tests\Unit\Helpers;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Helpers\Helpers;
use Core\Models\BaseModel;
use Core\Models\Traits\ListsColumns;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class HelpersTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Helpers
 */
class HelpersTest extends TestCase
{
    /**
     * Test `usesTrait` helper method.
     *
     * @return void
     */
    public function testUsesTrait()
    {
        $uses = Helpers::usesTrait(
            BaseModel::class,
            ListsColumns::class
        );

        $this->assertTrue($uses);

        $uses = Helpers::usesTrait(
            BaseModel::class,
            SoftDeletes::class
        );

        $this->assertFalse($uses);
    }

    /**
     * Test `objectToArray` helper method.
     *
     * @return void
     */
    public function testObjectToArray()
    {
        $data = [
            'email' => 'user@example.com',
            'age' => 42,
            'active' => true,
            'tags' => ['developer', 'admin'],
            'settings' => [
                'theme' => 'dark',
                'config' => (object) [
                    'timezone' => 'UTC',
                    'limit' => 50,
                ],
            ],
            'comments' => [
                (object) [
                    'author' => 'Alice',
                    'content' => 'Great work!',
                ],
                [
                    'author' => 'Bob',
                    'content' => 'Thanks for sharing',
                ],
            ],
        ];

        $result = Helpers::objectToArray((object) $data);

        $this->assertIsArray($result);
        $this->assertIsArray($result['tags']);
        $this->assertIsArray($result['settings']);
        $this->assertIsArray($result['settings']['config']);
        $this->assertIsArray($result['comments']);
        $this->assertIsArray($result['comments'][0]);
        $this->assertIsArray($result['comments'][1]);
    }

    /**
     * Test `requestedAppends` helper method.
     *
     * @return void
     */
    public function testRequestedAppends()
    {
        $appends = ['total', 'percentage'];
        $request = new Request([
            'append' => implode(',', $appends),
        ]);

        $requested = Helpers::requestedAppends($request);
        $this->assertEquals($appends, $requested);
    }

    /**
     * Test `requestedSorts` helper method.
     *
     * @return void
     */
    public function testRequestedSorts()
    {
        $request = new Request(['sort' => '-first,second']);
        $requested = Helpers::requestedSorts($request);

        $this->assertEquals(
            [
                'first' => 'desc',
                'second' => 'asc',
            ],
            $requested
        );
    }

    /**
     * Test `requestedIncludes` helper method.
     *
     * @return void
     */
    public function testRequestedIncludes()
    {
        $includes = ['author', 'pricing', 'variants.percentage'];
        $request = new Request([
            'include' => implode(',', $includes),
        ]);

        $requested = Helpers::requestedIncludes($request);
        $this->assertEquals(
            ['author', 'pricing', 'variants', 'variants.percentage'],
            $requested
        );
    }

    /**
     * Test `requestedFilters` helper method.
     *
     * @return void
     */
    public function testRequestedFilters()
    {
        $filters = [
            'first_name' => 'Jane',
            'last_name' => ['eq' => 'Smith'],
            'email' => [
                ['like' => '%smith%'],
                ['like' => '%@example.com'],
                ['not' => ['in' => 'jerry.smith@example,ben.smith@example']],
            ],
        ];
        $request = new Request([
            'filter' => $filters,
        ]);

        $requested = Helpers::requestedFilters($request);

        $this->assertEquals('first_name', $requested[0]['field']);
        $this->assertEquals('Jane', $requested[0]['value']);
        $this->assertEquals(FilterOperation::EQ, $requested[0]['operation']);
        $this->assertEquals([], $requested[0]['flags']);

        $this->assertEquals('last_name', $requested[1]['field']);
        $this->assertEquals('Smith', $requested[1]['value']);
        $this->assertEquals(FilterOperation::EQ, $requested[1]['operation']);
        $this->assertEquals([], $requested[1]['flags']);

        $this->assertEquals('email', $requested[2]['field']);
        $this->assertEquals('%smith%', $requested[2]['value']);
        $this->assertEquals(FilterOperation::LIKE, $requested[2]['operation']);
        $this->assertEquals([], $requested[2]['flags']);

        $this->assertEquals('email', $requested[3]['field']);
        $this->assertEquals('%@example.com', $requested[3]['value']);
        $this->assertEquals(FilterOperation::LIKE, $requested[3]['operation']);
        $this->assertEquals([], $requested[3]['flags']);

        $this->assertEquals('email', $requested[4]['field']);
        $this->assertEquals('jerry.smith@example,ben.smith@example', $requested[4]['value']);
        $this->assertEquals(FilterOperation::IN, $requested[4]['operation']);
        $this->assertEquals([FilterFlag::Not], $requested[4]['flags']);
    }

    /**
     * Test `extract` helper method.
     *
     * @return void
     */
    public function testExtract()
    {
        $key = 'key';
        $items = [
            1,
            [$key => 2],
            (object) [$key => 3],
        ];

        $extracted = Helpers::extract($key, ...$items);
        $this->assertEquals([1, 2, 3], $extracted);
    }

    /**
     * Test `morphOf` helper method.
     *
     * @return void
     */
    public function testMorphOf()
    {
        $this->assertEquals('resources', Helpers::morphOf('Resource'));
        $this->assertEquals('test.resources', Helpers::morphOf('Test\Resource'));
        $this->assertEquals('test.resources', Helpers::morphOf('Models\Test\Resource'));

        $this->assertEquals('complex-resources', Helpers::morphOf('ComplexResource'));
        $this->assertEquals('test.complex-resources', Helpers::morphOf('Test\ComplexResource'));
        $this->assertEquals('test.complex-resources', Helpers::morphOf('Models\Test\ComplexResource'));
    }

    public function testRequestedListsThatAreNotStringsAnswer422(): void
    {
        $cases = [
            'append' => fn (Request $request) => Helpers::requestedAppends($request),
            'include' => fn (Request $request) => Helpers::requestedIncludes($request),
            'sort' => fn (Request $request) => Helpers::requestedSorts($request),
        ];

        foreach ($cases as $key => $read) {
            try {
                $read(Request::create('/', 'GET', [$key => ['a', 'b']]));
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($key, $exception->errors());

                continue;
            }

            $this->fail("The $key parameter did not answer with 422.");
        }
    }
}
