<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Utf8\Tests;

use Symfony\Component\Utf8\Bytes;
use Symfony\Component\Utf8\CodePoints;
use Symfony\Component\Utf8\Graphemes;

/**
 * @requires PHP 7
 */
class CodePointsTest extends AbstractUtf8TestCase
{
    protected static function createFromString(string $string)
    {
        return CodePoints::fromString($string);
    }

    /**
     * @dataProvider provideCreateFromCodePointData
     */
    public function testCreateFromCodePoint(string $expected, array $codePoint)
    {
        $this->assertEquals(CodePoints::fromString($expected), call_user_func_array(array(CodePoints::class, 'fromCodePoint'), $codePoint));
    }

    public static function provideLength()
    {
        return array_merge(
            parent::provideLength(),
            array(
                // 8 instead of 5 if it was processed as a graphemes cluster
                array(8, 'अनुच्छेद'),
            )
        );
    }

    public function testToBytes()
    {
        $this->assertInstanceOf(Bytes::class, CodePoints::fromString('Symfony')->toBytes());
    }

    public function testToCodePoints()
    {
        $codePoints = CodePoints::fromString('Symfony');

        $this->assertSame($codePoints, $codePoints->toCodePoints());
    }

    public function testToGraphemes()
    {
        $this->assertInstanceOf(Graphemes::class, CodePoints::fromString('Symfony')->toGraphemes());
    }
}
