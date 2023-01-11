<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\WeekToArrayTransformer;

class WeekToArrayTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new WeekToArrayTransformer();

        $this->assertSame(['year' => 2019, 'week' => 1], $transformer->transform('2019-W01'));
    }

    public function testTransformEmpty()
    {
        $transformer = new WeekToArrayTransformer();

        $this->assertSame(['year' => null, 'week' => null], $transformer->transform(null));
    }

    /**
     * @dataProvider transformationFailuresProvider
     */
    public function testTransformationFailures($input, string $message)
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage($message);

        $transformer = new WeekToArrayTransformer();
        $transformer->transform($input);
    }

    public static function transformationFailuresProvider(): array
    {
        return [
            'malformed string' => ['lorem', 'Given data does not follow the date format "Y-\WW".'],
            'non-string' => [[], 'Value is expected to be a string but was "array".'],
        ];
    }

    public function testReverseTransform()
    {
        $transformer = new WeekToArrayTransformer();

        $input = [
            'year' => 2019,
            'week' => 1,
        ];

        $this->assertEquals('2019-W01', $transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty()
    {
        $transformer = new WeekToArrayTransformer();

        $input = [
            'year' => null,
            'week' => null,
        ];

        $this->assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformNull()
    {
        $transformer = new WeekToArrayTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new WeekToArrayTransformer();

        $this->assertNull($transformer->reverseTransform([]));
    }

    /**
     * @dataProvider reverseTransformationFailuresProvider
     */
    public function testReverseTransformFailures($input, string $message)
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage($message);

        $transformer = new WeekToArrayTransformer();
        $transformer->reverseTransform($input);
    }

    public static function reverseTransformationFailuresProvider(): array
    {
        return [
            'missing year' => [['week' => 1], 'Key "year" is missing.'],
            'missing week' => [['year' => 2019], 'Key "week" is missing.'],
            'integer instead of array' => [0, 'Value is expected to be an array, but was "int"'],
            'string instead of array' => ['12345', 'Value is expected to be an array, but was "string"'],
            'week invalid' => [['year' => 2019, 'week' => 66], 'Week "66" does not exist for year "2019".'],
            'year null' => [['year' => null, 'week' => 1], 'Year is expected to be an integer, but was "null".'],
            'week null' => [['year' => 2019, 'week' => null], 'Week is expected to be an integer, but was "null".'],
            'year non-integer' => [['year' => '2019', 'week' => 1], 'Year is expected to be an integer, but was "string".'],
            'week non-integer' => [['year' => 2019, 'week' => '1'], 'Week is expected to be an integer, but was "string".'],
            'unexpected key' => [['year' => 2019, 'bar' => 'baz', 'week' => 1, 'foo' => 'foobar'], 'Expected only keys "year" and "week" to be present, but also got ["bar", "foo"].'],
        ];
    }
}
