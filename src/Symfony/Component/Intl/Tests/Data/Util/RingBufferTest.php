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
    private RingBuffer $buffer;

    protected function setUp(): void
    {
        $this->buffer = new RingBuffer(2);
    }

    public function testWriteWithinBuffer(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';

        $this->assertArrayHasKey(0, $this->buffer);
        $this->assertArrayHasKey('bar', $this->buffer);
        $this->assertSame('foo', $this->buffer[0]);
        $this->assertSame('baz', $this->buffer['bar']);
    }

    public function testWritePastBuffer(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->assertArrayHasKey('bar', $this->buffer);
        $this->assertArrayHasKey(2, $this->buffer);
        $this->assertSame('baz', $this->buffer['bar']);
        $this->assertSame('bam', $this->buffer[2]);
    }

    public function testReadNonExistingFails(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->buffer['foo'];
    }

    public function testQueryNonExisting(): void
    {
        $this->assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testUnsetNonExistingSucceeds(): void
    {
        unset($this->buffer['foo']);

        $this->assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testReadOverwrittenFails(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->buffer[0];
    }

    public function testQueryOverwritten(): void
    {
        $this->assertArrayNotHasKey(0, $this->buffer);
    }

    public function testUnsetOverwrittenSucceeds(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        unset($this->buffer[0]);

        $this->assertArrayNotHasKey(0, $this->buffer);
    }
}
