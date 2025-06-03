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
     * @testWith [""]
     *           ["valid string"]
     */
    public function testNormalize(string $value)
    {
        $node = new StringNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    /**
     * @testWith [null]
     *           [false]
     *           [true]
     *           [0]
     *           [1]
     *           [0.0]
     *           [0.1]
     *           [{}]
     *           [{"foo": "bar"}]
     */
    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $node = new StringNode('test');

        $this->expectException(InvalidTypeException::class);

        $node->normalize($value);
    }
}
