<?php

namespace Core\Tests\Unit\Helpers;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Helpers\FilterHelper;
use Core\Tests\TestCase;
use InvalidArgumentException;

/**
 * Class FilterHelperTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Helpers
 */
class FilterHelperTest extends TestCase
{
    /**
     * Test that a node, which is a plain value or names the `eq` operation, is parsed as an equality filter without
     * flags.
     *
     * @return void
     */
    public function testParseNodeValid(): void
    {
        $parsed = FilterHelper::parseNode('name', 'John');
        $data = array_first($parsed);

        $this->assertEquals('name', $data['field']);
        $this->assertEquals(FilterOperation::EQ, $data['operation']);
        $this->assertEquals('John', $data['value']);
        $this->assertEquals([], $data['flags']);

        $parsed = FilterHelper::parseNode('name', ['eq' => 'John']);
        $data = array_first($parsed);

        $this->assertEquals('name', $data['field']);
        $this->assertEquals(FilterOperation::EQ, $data['operation']);
        $this->assertEquals('John', $data['value']);
        $this->assertEquals([], $data['flags']);
    }

    /**
     * Test that the `ci` and `not` flags of a node are parsed in their order, with the operation, which defaults to
     * `eq`, when it is not named.
     *
     * @return void
     */
    public function testParseNodeValidWithFlags(): void
    {
        $parsed = FilterHelper::parseNode('name', ['ci' => 'John']);
        $data = array_first($parsed);

        $this->assertEquals('name', $data['field']);
        $this->assertEquals(FilterOperation::EQ, $data['operation']);
        $this->assertEquals('John', $data['value']);
        $this->assertEquals([FilterFlag::CaseInsensitive], $data['flags']);

        $parsed = FilterHelper::parseNode('name', ['ci' => ['eq' => 'John']]);
        $data = array_first($parsed);

        $this->assertEquals('name', $data['field']);
        $this->assertEquals(FilterOperation::EQ, $data['operation']);
        $this->assertEquals('John', $data['value']);
        $this->assertEquals([FilterFlag::CaseInsensitive], $data['flags']);

        $parsed = FilterHelper::parseNode('name', ['not' => ['ci' => ['eq' => 'John']]]);
        $data = array_first($parsed);

        $this->assertEquals('name', $data['field']);
        $this->assertEquals(FilterOperation::EQ, $data['operation']);
        $this->assertEquals('John', $data['value']);
        $this->assertEquals([FilterFlag::Not, FilterFlag::CaseInsensitive], $data['flags']);
    }

    /**
     * Test that a node, which puts a flag after the operation, is refused.
     *
     * @return void
     */
    public function testParseNodeInvalidByOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterHelper::parseNode('name', ['eq' => ['not' => 'John']]);
    }

    /**
     * Test that a node, which nests one operation in another, is refused.
     *
     * @return void
     */
    public function testParseNodeInvalidByTwoOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterHelper::parseNode('name', ['eq' => ['gt' => 'John']]);
    }

    /**
     * Test that a node with a null value is refused, as a filter value must be a string or a list of strings.
     *
     * @return void
     */
    public function testParseNodeInvalidByNullValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A filter value must be a string or a list of strings.');

        FilterHelper::parseNode('name', ['eq' => null]);
    }

    /**
     * Test that a node with an unknown key is refused with a message, which lists the valid operations and flags.
     *
     * @return void
     */
    public function testParseNodeInvalidByUnknownKeyNamesTheValidOnes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Unknown filter operation or flag 'foo'. "
            . 'Operations: eq, in, null, like, contains, gt, gte, lt, lte; flags: not, ci.'
        );

        FilterHelper::parseNode('name', ['foo' => 'John']);
    }

    /**
     * Test that the filters of a field are parsed with their operation, value and flags.
     *
     * @return void
     */
    public function testParseValid(): void
    {
        $data = FilterHelper::parse(['name' => ['like' => '%John%']]);

        $this->assertEquals('name', $data[0]['field']);
        $this->assertEquals(FilterOperation::LIKE, $data[0]['operation']);
        $this->assertEquals('%John%', $data[0]['value']);
        $this->assertEquals([], $data[0]['flags']);

        $data = FilterHelper::parse(['name' => ['ci' => ['like' => '%John%']]]);

        $this->assertEquals('name', $data[0]['field']);
        $this->assertEquals(FilterOperation::LIKE, $data[0]['operation']);
        $this->assertEquals('%John%', $data[0]['value']);
        $this->assertEquals([FilterFlag::CaseInsensitive], $data[0]['flags']);
    }

    /**
     * Test that a list of nodes for one field is parsed into a filter per node, in their order.
     *
     * @return void
     */
    public function testParseValidMultiple(): void
    {
        $filters = [
            'name' => [
                ['like' => '%John%'],
                ['like' => '%Smith%'],
            ],
        ];

        $data = FilterHelper::parse($filters);

        $this->assertEquals('name', $data[0]['field']);
        $this->assertEquals(FilterOperation::LIKE, $data[0]['operation']);
        $this->assertEquals('%John%', $data[0]['value']);
        $this->assertEquals([], $data[0]['flags']);

        $this->assertEquals('name', $data[1]['field']);
        $this->assertEquals(FilterOperation::LIKE, $data[1]['operation']);
        $this->assertEquals('%Smith%', $data[1]['value']);
        $this->assertEquals([], $data[1]['flags']);
    }
}
