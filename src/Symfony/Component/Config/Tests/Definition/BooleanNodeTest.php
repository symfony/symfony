<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

class BooleanNodeTest extends TestCase
{
    /**
     * @dataProvider getValidValues
     */
    public function testNormalize(bool $value)
    {
        $node = new BooleanNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    public function testNullValueOnNullable()
    {
        $node = new BooleanNode('test', null, '.', true);

        $this->assertNull($node->normalize(null));
    }

    public function testNullValueOnNotNullable()
    {
        $node = new BooleanNode('test', null, '.', false);

        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid type for path "test". Expected "bool", but got "null".');

        $this->assertNull($node->normalize(null));
    }

    public function testInvalidValueOnNullable()
    {
        $node = new BooleanNode('test', null, '.', true);

        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid type for path "test". Expected "bool" or "null", but got "int".');

        $node->normalize(123);
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidNonEmptyValues(bool $value)
    {
        $node = new BooleanNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public static function getValidValues(): array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $node = new BooleanNode('test');

        $this->expectException(InvalidTypeException::class);

        $node->normalize($value);
    }

    public static function getInvalidValues(): array
    {
        return [
            [''],
            ['foo'],
            [0],
            [1],
            [0.0],
            [0.1],
            [[]],
            [['foo' => 'bar']],
            [new \stdClass()],
        ];
    }
}
