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

    private $chainDecoder;
    private $decoder1;
    private $decoder2;

    protected function setUp(): void
    {
        $this->decoder1 = $this->createMock(ContextAwareDecoderInterface::class);
        $this->decoder1
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
                [self::FORMAT_3, ['foo' => 'bar2'], false],
            ]);

        $this->decoder2 = $this->createMock(DecoderInterface::class);
        $this->decoder2
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], false],
                [self::FORMAT_2, [], true],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], false],
                [self::FORMAT_3, ['foo' => 'bar2'], true],
            ]);

        $this->chainDecoder = new ChainDecoder([$this->decoder1, $this->decoder2]);
    }

    public function testSupportsDecoding()
    {
        $this->decoder1
            ->method('decode')
            ->willReturn('result1');
        $this->decoder2
            ->method('decode')
            ->willReturn('result2');

        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_1));
        $this->assertEquals('result1', $this->chainDecoder->decode('', self::FORMAT_1, []));

        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_2));
        $this->assertEquals('result2', $this->chainDecoder->decode('', self::FORMAT_2, []));

        $this->assertFalse($this->chainDecoder->supportsDecoding(self::FORMAT_3));

        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_3, ['foo' => 'bar']));
        $this->assertEquals('result1', $this->chainDecoder->decode('', self::FORMAT_3, ['foo' => 'bar']));

        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_3, ['foo' => 'bar2']));
        $this->assertEquals('result2', $this->chainDecoder->decode('', self::FORMAT_3, ['foo' => 'bar2']));
    }

    public function testDecode()
    {
        $this->decoder1->expects($this->never())->method('decode');
        $this->decoder2->expects($this->once())->method('decode');

        $this->chainDecoder->decode('string_to_decode', self::FORMAT_2);
    }

    public function testDecodeUnsupportedFormat()
    {
        $this->expectException(RuntimeException::class);
        $this->chainDecoder->decode('string_to_decode', self::FORMAT_3);
    }
}
