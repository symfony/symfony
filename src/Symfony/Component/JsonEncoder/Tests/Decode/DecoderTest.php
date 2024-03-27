<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\NativeDecoder;
use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;

class DecoderTest extends TestCase
{
    public function testDecode()
    {
        $this->assertDecoded('foo', '"foo"');
    }

    public function testDecodeSubset()
    {
        $this->assertDecoded('bar', '["foo","bar","baz"]', 7, 5);
    }

    public function testDecodeThrowOnInvalidJsonString()
    {
        $this->expectException(UnexpectedValueException::class);

        NativeDecoder::decodeString('foo"');
    }

    public function testDecodeThrowOnInvalidJsonStream()
    {
        $this->expectException(UnexpectedValueException::class);

        $stream = new BufferedStream();
        $stream->write('foo"');
        $stream->rewind();

        NativeDecoder::decodeStream($stream);
    }

    private function assertDecoded(mixed $decoded, string $encoded, int $offset = 0, ?int $length = null): void
    {
        if (0 === $offset && null === $length) {
            $this->assertEquals($decoded, NativeDecoder::decodeString($encoded));
        }

        $stream = new BufferedStream();
        $stream->write($encoded);
        $stream->rewind();

        $this->assertEquals($decoded, NativeDecoder::decodeStream($stream, $offset, $length));

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $encoded);
        rewind($resource);

        $this->assertEquals($decoded, NativeDecoder::decodeStream($resource, $offset, $length));
    }
}
