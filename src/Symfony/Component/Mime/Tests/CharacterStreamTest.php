<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\CharacterStream;

class CharacterStreamTest extends TestCase
{
    public function testReadCharactersAreInTact()
    {
        $stream = new CharacterStream(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE));
        $stream->write(pack('C*',
            0xD0, 0xBB,
            0xD1, 0x8E,
            0xD0, 0xB1,
            0xD1, 0x8B,
            0xD1, 0x85
        ));
        $this->assertSame(pack('C*', 0xD0, 0x94), $stream->read(1));
        $this->assertSame(pack('C*', 0xD0, 0xB6, 0xD0, 0xBE), $stream->read(2));
        $this->assertSame(pack('C*', 0xD0, 0xBB), $stream->read(1));
        $this->assertSame(pack('C*', 0xD1, 0x8E, 0xD0, 0xB1, 0xD1, 0x8B), $stream->read(3));
        $this->assertSame(pack('C*', 0xD1, 0x85), $stream->read(1));
        $this->assertNull($stream->read(1));
    }

    public function testCharactersCanBeReadAsByteArrays()
    {
        $stream = new CharacterStream(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE));
        $stream->write(pack('C*',
            0xD0, 0xBB,
            0xD1, 0x8E,
            0xD0, 0xB1,
            0xD1, 0x8B,
            0xD1, 0x85
        ));
        $this->assertEquals([0xD0, 0x94], $stream->readBytes(1));
        $this->assertEquals([0xD0, 0xB6, 0xD0, 0xBE], $stream->readBytes(2));
        $this->assertEquals([0xD0, 0xBB], $stream->readBytes(1));
        $this->assertEquals([0xD1, 0x8E, 0xD0, 0xB1, 0xD1, 0x8B], $stream->readBytes(3));
        $this->assertEquals([0xD1, 0x85], $stream->readBytes(1));
        $this->assertNull($stream->readBytes(1));
    }

    public function testRequestingLargeCharCountPastEndOfStream()
    {
        $stream = new CharacterStream(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE));
        $this->assertSame(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE), $stream->read(100));
        $this->assertNull($stream->read(1));
    }

    public function testRequestingByteArrayCountPastEndOfStream()
    {
        $stream = new CharacterStream(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE));
        $this->assertEquals([0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE], $stream->readBytes(100));
        $this->assertNull($stream->readBytes(1));
    }

    public function testPointerOffsetCanBeSet()
    {
        $stream = new CharacterStream(pack('C*', 0xD0, 0x94, 0xD0, 0xB6, 0xD0, 0xBE));
        $this->assertSame(pack('C*', 0xD0, 0x94), $stream->read(1));
        $stream->setPointer(0);
        $this->assertSame(pack('C*', 0xD0, 0x94), $stream->read(1));
        $stream->setPointer(2);
        $this->assertSame(pack('C*', 0xD0, 0xBE), $stream->read(1));
    }

    public function testAlgorithmWithFixedWidthCharsets()
    {
        $stream = new CharacterStream(pack('C*', 0xD1, 0x8D, 0xD0, 0xBB, 0xD0, 0xB0));
        $this->assertSame(pack('C*', 0xD1, 0x8D), $stream->read(1));
        $this->assertSame(pack('C*', 0xD0, 0xBB), $stream->read(1));
        $this->assertSame(pack('C*', 0xD0, 0xB0), $stream->read(1));
        $this->assertNull($stream->read(1));
    }
}
