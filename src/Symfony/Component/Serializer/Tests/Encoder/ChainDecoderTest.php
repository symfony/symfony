<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\ChainDecoder;
use Symfony\Component\Serializer\Encoder\ContextAwareDecoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;

class ChainDecoderTest extends TestCase
{
    private const FORMAT_1 = 'format1';
    private const FORMAT_2 = 'format2';
    private const FORMAT_3 = 'format3';

    public function testSupportsDecoding()
    {
        $decoder1 = $this->createDecoder1();
        $decoder1
            ->method('decode')
            ->willReturn('result1');
        $decoder2 = $this->createDecoder2();
        $decoder2
            ->method('decode')
            ->willReturn('result2');
        $chainDecoder = new ChainDecoder([$decoder1, $decoder2]);

        $this->assertTrue($chainDecoder->supportsDecoding(self::FORMAT_1));
        $this->assertEquals('result1', $chainDecoder->decode('', self::FORMAT_1, []));

        $this->assertTrue($chainDecoder->supportsDecoding(self::FORMAT_2));
        $this->assertEquals('result2', $chainDecoder->decode('', self::FORMAT_2, []));

        $this->assertFalse($chainDecoder->supportsDecoding(self::FORMAT_3));

        $this->assertTrue($chainDecoder->supportsDecoding(self::FORMAT_3, ['foo' => 'bar']));
        $this->assertEquals('result1', $chainDecoder->decode('', self::FORMAT_3, ['foo' => 'bar']));

        $this->assertTrue($chainDecoder->supportsDecoding(self::FORMAT_3, ['foo' => 'bar2']));
        $this->assertEquals('result2', $chainDecoder->decode('', self::FORMAT_3, ['foo' => 'bar2']));
    }

    public function testDecode()
    {
        $decoder1 = $this->createDecoder1(true);
        $decoder1->expects($this->never())->method('decode');
        $decoder2 = $this->createDecoder2(true);
        $decoder2->expects($this->once())->method('decode');
        $chainDecoder = new ChainDecoder([$decoder1, $decoder2]);

        $chainDecoder->decode('string_to_decode', self::FORMAT_2);
    }

    public function testDecodeUnsupportedFormat()
    {
        $chainDecoder = new ChainDecoder([$this->createDecoder1(), $this->createDecoder2()]);
        $this->expectException(RuntimeException::class);
        $chainDecoder->decode('string_to_decode', self::FORMAT_3);
    }

    private function createDecoder1(bool $mock = false): DecoderInterface
    {
        if ($mock) {
            $decoder = $this->createMock(ContextAwareDecoderInterface::class);
        } else {
            $decoder = $this->createStub(ContextAwareDecoderInterface::class);
        }

        $decoder
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
                [self::FORMAT_3, ['foo' => 'bar2'], false],
            ]);

        return $decoder;
    }

    private function createDecoder2(bool $mock = false): DecoderInterface
    {
        if ($mock) {
            $decoder = $this->createMock(DecoderInterface::class);
        } else {
            $decoder = $this->createStub(DecoderInterface::class);
        }

        $decoder
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], false],
                [self::FORMAT_2, [], true],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], false],
                [self::FORMAT_3, ['foo' => 'bar2'], true],
            ]);

        return $decoder;
    }
}
