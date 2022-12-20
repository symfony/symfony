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
        self::assertSame($value, $node->normalize($value));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidNonEmptyValues(bool $value)
    {
        $node = new BooleanNode('test');
        $node->setAllowEmptyValue(false);

        self::assertSame($value, $node->finalize($value));
    }

    public function getValidValues(): array
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
        self::expectException(InvalidTypeException::class);
        $node = new BooleanNode('test');
        $node->normalize($value);
    }

    public function getInvalidValues(): array
    {
        return [
            [null],
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
