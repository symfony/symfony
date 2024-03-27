<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Stream;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;
use Symfony\Component\JsonEncoder\Stream\MemoryStream;
use Symfony\Component\JsonEncoder\Stream\StreamReaderInterface;
use Symfony\Component\JsonEncoder\Stream\StreamWriterInterface;

class StreamTest extends TestCase
{
    /**
     * @dataProvider streams
     */
    public function testRead(StreamReaderInterface&StreamWriterInterface $stream)
    {
        $stream->write('123456789');
        $stream->rewind();

        $this->assertSame('1', $stream->read(1));
        $this->assertSame('23', $stream->read(2));
        $this->assertSame('456789', $stream->read());
    }

    /**
     * @dataProvider streams
     */
    public function testSeek(StreamReaderInterface&StreamWriterInterface $stream)
    {
        $stream->write('123456789');

        $stream->seek(1);
        $this->assertSame('2', $stream->read(1));

        $stream->seek(1);
        $this->assertSame('234', $stream->read(3));
    }

    /**
     * @dataProvider streams
     */
    public function testIterateOverStream(StreamReaderInterface&StreamWriterInterface $stream)
    {
        $stream->write(str_repeat('.', 20000));
        $stream->seek(10);

        $this->assertSame([str_repeat('.', 8192), str_repeat('.', 8192), str_repeat('.', 3606)], iterator_to_array($stream));
    }

    /**
     * @dataProvider streams
     */
    public function testCastToString(StreamReaderInterface&StreamWriterInterface $stream)
    {
        $stream->write(str_repeat('.', 10000));
        $stream->seek(10);

        $this->assertSame(str_repeat('.', 9990), (string) $stream);
    }

    /**
     * @dataProvider streams
     */
    public function testWrite(StreamReaderInterface&StreamWriterInterface $stream)
    {
        $stream->write('123456789');
        $this->assertSame('', $stream->read());

        $stream->rewind();
        $this->assertSame('123456789', $stream->read());
    }

    /**
     * @return iterable<array{0: StreamReaderInterface&StreamWriterInterface}>
     */
    public static function streams(): iterable
    {
        yield [new BufferedStream()];
        yield [new MemoryStream()];
    }
}
