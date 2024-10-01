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
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\StringNode;

class StringNodeTest extends TestCase
{
    /**
     * @dataProvider getValidValues
     */
    public function testNormalize(string $value): void
    {
        $node = new StringNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    public static function getValidValues(): array
    {
        return [
            [''],
            ['valid string'],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testNormalizeThrowsExceptionOnInvalidValues($value): void
    {

        $node = new StringNode('test');

        $this->expectException(InvalidTypeException::class);

        $node->normalize($value);
    }

    public static function getInvalidValues(): array
    {
        return [
            [null],
            [false],
            [true],
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
