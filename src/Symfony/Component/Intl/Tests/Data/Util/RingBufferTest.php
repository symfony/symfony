<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Util\RingBuffer;
use Symfony\Component\Intl\Exception\OutOfBoundsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RingBufferTest extends TestCase
{
    /**
     * @var RingBuffer
     */
    private $buffer;

    protected function setUp(): void
    {
        $this->buffer = new RingBuffer(2);
    }

    public function testWriteWithinBuffer()
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';

        self::assertArrayHasKey(0, $this->buffer);
        self::assertArrayHasKey('bar', $this->buffer);
        self::assertSame('foo', $this->buffer[0]);
        self::assertSame('baz', $this->buffer['bar']);
    }

    public function testWritePastBuffer()
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        self::assertArrayHasKey('bar', $this->buffer);
        self::assertArrayHasKey(2, $this->buffer);
        self::assertSame('baz', $this->buffer['bar']);
        self::assertSame('bam', $this->buffer[2]);
    }

    public function testReadNonExistingFails()
    {
        self::expectException(OutOfBoundsException::class);
        $this->buffer['foo'];
    }

    public function testQueryNonExisting()
    {
        self::assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testUnsetNonExistingSucceeds()
    {
        unset($this->buffer['foo']);

        self::assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testReadOverwrittenFails()
    {
        self::expectException(OutOfBoundsException::class);
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->buffer[0];
    }

    public function testQueryOverwritten()
    {
        self::assertArrayNotHasKey(0, $this->buffer);
    }

    public function testUnsetOverwrittenSucceeds()
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        unset($this->buffer[0]);

        self::assertArrayNotHasKey(0, $this->buffer);
    }
}
