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

    public function testWriteWithinBuffer(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';

        $this->assertTrue(isset($this->buffer[0]));
        $this->assertTrue(isset($this->buffer['bar']));
        $this->assertSame('foo', $this->buffer[0]);
        $this->assertSame('baz', $this->buffer['bar']);
    }

    public function testWritePastBuffer(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->assertTrue(isset($this->buffer['bar']));
        $this->assertTrue(isset($this->buffer[2]));
        $this->assertSame('baz', $this->buffer['bar']);
        $this->assertSame('bam', $this->buffer[2]);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\OutOfBoundsException
     */
    public function testReadNonExistingFails(): void
    {
        $this->buffer['foo'];
    }

    public function testQueryNonExisting(): void
    {
        $this->assertFalse(isset($this->buffer['foo']));
    }

    public function testUnsetNonExistingSucceeds(): void
    {
        unset($this->buffer['foo']);

        $this->assertFalse(isset($this->buffer['foo']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\OutOfBoundsException
     */
    public function testReadOverwrittenFails(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        $this->buffer[0];
    }

    public function testQueryOverwritten(): void
    {
        $this->assertFalse(isset($this->buffer[0]));
    }

    public function testUnsetOverwrittenSucceeds(): void
    {
        $this->buffer[0] = 'foo';
        $this->buffer['bar'] = 'baz';
        $this->buffer[2] = 'bam';

        unset($this->buffer[0]);

        $this->assertFalse(isset($this->buffer[0]));
    }
}
