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

class ChainDecoderTest extends TestCase
{
    const FORMAT_1 = 'format1';
    const FORMAT_2 = 'format2';
    const FORMAT_3 = 'format3';

    private $chainDecoder;
    private $decoder1;
    private $decoder2;

    protected function setUp()
    {
        $this->decoder1 = $this
            ->getMockBuilder('Symfony\Component\Serializer\Encoder\DecoderInterface')
            ->getMock();

        $this->decoder1
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
            ]);

        $this->decoder2 = $this
            ->getMockBuilder('Symfony\Component\Serializer\Encoder\DecoderInterface')
            ->getMock();

        $this->decoder2
            ->method('supportsDecoding')
            ->willReturnMap([
                [self::FORMAT_1, [], false],
                [self::FORMAT_2, [], true],
                [self::FORMAT_3, [], false],
            ]);

        $this->chainDecoder = new ChainDecoder([$this->decoder1, $this->decoder2]);
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_1));
        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_2));
        $this->assertFalse($this->chainDecoder->supportsDecoding(self::FORMAT_3));
        $this->assertTrue($this->chainDecoder->supportsDecoding(self::FORMAT_3, ['foo' => 'bar']));
    }

    public function testDecode()
    {
        $this->decoder1->expects($this->never())->method('decode');
        $this->decoder2->expects($this->once())->method('decode');

        $this->chainDecoder->decode('string_to_decode', self::FORMAT_2);
    }

    public function testDecodeUnsupportedFormat()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\RuntimeException');
        $this->chainDecoder->decode('string_to_decode', self::FORMAT_3);
    }
}
