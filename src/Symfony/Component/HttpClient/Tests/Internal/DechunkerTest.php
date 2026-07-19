<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\Dechunker;

class DechunkerTest extends TestCase
{
    /**
     * @dataProvider provideValidChunkedData
     */
    public function testDechunk(string $expected, string $chunked)
    {
        $dechunker = new Dechunker();

        $this->assertSame($expected, $dechunker->dechunk($chunked));
        $this->assertTrue($dechunker->isFinished());
    }

    /**
     * @dataProvider provideValidChunkedData
     */
    public function testDechunkByteByByte(string $expected, string $chunked)
    {
        $dechunker = new Dechunker();
        $out = '';

        foreach (str_split($chunked) as $byte) {
            $out .= $dechunker->dechunk($byte);
        }

        $this->assertSame($expected, $out);
        $this->assertTrue($dechunker->isFinished());
    }

    public static function provideValidChunkedData(): iterable
    {
        yield 'single chunk' => ['hello world', "b\r\nhello world\r\n0\r\n\r\n"];
        yield 'multiple chunks' => ['Symfony is awesome!', "8\r\nSymfony \r\n5\r\nis aw\r\n6\r\nesome!\r\n0\r\n\r\n"];
        yield 'uppercase hex and leading zeros' => ['hello world', "0B\r\nhello world\r\n000\r\n\r\n"];
        yield 'chunk extensions' => ['hello world', "b;foo=bar\r\nhello world\r\n0;baz\r\n\r\n"];
        yield 'bare LF line endings' => ['hello world', "b\nhello world\n0\n"];
        yield 'trailer fields' => ['hello world', "b\r\nhello world\r\n0\r\nX-Trailer: value\r\n\r\n"];
        yield 'data after terminal chunk is ignored' => ['hello world', "b\r\nhello world\r\n0\r\n\r\nignored"];
        yield 'CRLF inside chunk data' => ["\r\n\r\n", "4\r\n\r\n\r\n\r\n0\r\n\r\n"];
        yield 'empty body' => ['', "0\r\n\r\n"];
    }

    /**
     * @dataProvider provideTruncatedChunkedData
     */
    public function testTruncatedData(string $chunked)
    {
        $dechunker = new Dechunker();
        $dechunker->dechunk($chunked);

        $this->assertFalse($dechunker->isFinished());
    }

    public static function provideTruncatedChunkedData(): iterable
    {
        yield 'empty' => [''];
        yield 'partial size' => ['b'];
        yield 'partial data' => ["b\r\nhel"];
        yield 'missing terminal chunk' => ["b\r\nhello world\r\n"];
        yield 'partial terminal chunk' => ["b\r\nhello world\r\n0"];
    }

    /**
     * @dataProvider provideInvalidChunkedData
     */
    public function testInvalidData(string $chunked)
    {
        $dechunker = new Dechunker();

        $this->expectException(TransportException::class);
        $dechunker->dechunk($chunked);
    }

    public static function provideInvalidChunkedData(): iterable
    {
        yield 'invalid size' => ["x\r\nhello\r\n"];
        yield 'empty size line' => ["\r\nhello\r\n"];
        yield 'missing LF after size' => ["5\rXhello"];
        yield 'missing line ending after data' => ["1\r\naX"];
        yield 'CR but no LF after data' => ["1\r\na\rX"];
        yield 'size overflow' => ["123456789abcdef01\r\n"];
    }
}
