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

        $this->assertArrayHasKey(0, $this->buffer);
        $this->assertArrayHasKey('bar', $this->buffer);
        $this->assertSame('foo', $this->buffer[0]);
        $this->assertSame('baz', $this->buffer['bar']);
    }

    public function testWritePastBuffer()
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->assertArrayHasKey('bar', $this->buffer);
        $this->assertArrayHasKey(2, $this->buffer);
        $this->assertSame('baz', $this->buffer['bar']);
        $this->assertSame('bam', $this->buffer[2]);
    }

    public function testReadNonExistingFails()
    {
        $this->expectException('Symfony\Component\Intl\Exception\OutOfBoundsException');
        $this->buffer['foo'];
    }

    public function testQueryNonExisting()
    {
        $this->assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testUnsetNonExistingSucceeds()
    {
        unset($this->buffer['foo']);

        $this->assertArrayNotHasKey('foo', $this->buffer);
    }

    public function testReadOverwrittenFails()
    {
        $this->expectException('Symfony\Component\Intl\Exception\OutOfBoundsException');
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->buffer[0];
    }

    public function testQueryOverwritten()
    {
        $this->assertArrayNotHasKey(0, $this->buffer);
    }

    public function testUnsetOverwrittenSucceeds()
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        unset($this->buffer[0]);

        $this->assertArrayNotHasKey(0, $this->buffer);
    }
}
