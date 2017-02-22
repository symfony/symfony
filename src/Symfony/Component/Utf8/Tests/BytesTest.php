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
class BytesTest extends AbstractAsciiTestCase
{
    protected static function createFromString(string $string)
    {
        return Bytes::fromString($string);
    }

    public function testFromCharCode()
    {
        $this->assertEquals(Bytes::fromString(''), Bytes::fromCharCode());
        $this->assertEquals(Bytes::fromString('H'), Bytes::fromCharCode(72));
        $this->assertEquals(Bytes::fromString('Hello World!'), Bytes::fromCharCode(72, 101, 108, 108, 111, 32, 87, 111, 114, 108, 100, 33));
    }

    public function testToBytes()
    {
        $bytes = Bytes::fromString('Symfony');

        $this->assertSame($bytes, $bytes->toBytes());
    }

    public function testToCodePoints()
    {
        $this->assertInstanceOf(CodePoints::class, Bytes::fromString('Symfony')->toCodePoints());
    }

    public function testToGraphemes()
    {
        $this->assertInstanceOf(Graphemes::class, Bytes::fromString('Symfony')->toGraphemes());
    }
}
