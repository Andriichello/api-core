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

    public function testParseNodeInvalidByOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterHelper::parseNode('name', ['eq' => ['not' => 'John']]);
    }

    public function testParseNodeInvalidByTwoOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterHelper::parseNode('name', ['eq' => ['gt' => 'John']]);
    }

    public function testParseNodeInvalidByNullValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A filter value must be a string or a list of strings.');

        FilterHelper::parseNode('name', ['eq' => null]);
    }

    public function testParseNodeInvalidByUnknownKeyNamesTheValidOnes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Unknown filter operation or flag 'foo'. "
            . 'Operations: eq, in, null, like, gt, gte, lt, lte; flags: not, ci.'
        );

        FilterHelper::parseNode('name', ['foo' => 'John']);
    }

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
