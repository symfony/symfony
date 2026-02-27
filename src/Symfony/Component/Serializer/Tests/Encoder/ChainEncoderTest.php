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
use Symfony\Component\Serializer\Debug\TraceableEncoder;
use Symfony\Component\Serializer\Encoder\ChainEncoder;
use Symfony\Component\Serializer\Encoder\ContextAwareEncoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\NormalizationAwareInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;

class ChainEncoderTest extends TestCase
{
    private const FORMAT_1 = 'format1';
    private const FORMAT_2 = 'format2';
    private const FORMAT_3 = 'format3';

    public function testSupportsEncoding()
    {
        $encoder1 = $this->createEncoder1();
        $encoder1
            ->method('encode')
            ->willReturn('result1');
        $encoder2 = $this->createEncoder2();
        $encoder2
            ->method('encode')
            ->willReturn('result2');

        $chainEncoder = new ChainEncoder([$encoder1, $encoder2]);

        $this->assertTrue($chainEncoder->supportsEncoding(self::FORMAT_1));
        $this->assertEquals('result1', $chainEncoder->encode('', self::FORMAT_1, []));

        $this->assertTrue($chainEncoder->supportsEncoding(self::FORMAT_2));
        $this->assertEquals('result2', $chainEncoder->encode('', self::FORMAT_2, []));

        $this->assertFalse($chainEncoder->supportsEncoding(self::FORMAT_3));

        $this->assertTrue($chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar']));
        $this->assertEquals('result1', $chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar']));

        $this->assertTrue($chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar2']));
        $this->assertEquals('result2', $chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar2']));
    }

    public function testEncode()
    {
        $encoder1 = $this->createEncoder1(true);
        $encoder1->expects($this->never())->method('encode');
        $encoder2 = $this->createEncoder2(true);
        $encoder2->expects($this->once())->method('encode')->willReturn('foo:123');

        $chainEncoder = new ChainEncoder([$encoder1, $encoder2]);

        $this->assertSame('foo:123', $chainEncoder->encode(['foo' => 123], self::FORMAT_2));
    }

    public function testEncodeUnsupportedFormat()
    {
        $chainEncoder = new ChainEncoder([$this->createEncoder1(), $this->createEncoder2()]);
        $this->expectException(RuntimeException::class);
        $chainEncoder->encode(['foo' => 123], self::FORMAT_3);
    }

    public function testNeedsNormalizationBasic()
    {
        $chainEncoder = new ChainEncoder([$this->createEncoder1(), $this->createEncoder2()]);

        $this->assertTrue($chainEncoder->needsNormalization(self::FORMAT_1));
        $this->assertTrue($chainEncoder->needsNormalization(self::FORMAT_2));
    }

    public function testNeedsNormalizationNormalizationAware()
    {
        $encoder = new NormalizationAwareEncoder();
        $sut = new ChainEncoder([$encoder]);

        $this->assertFalse($sut->needsNormalization(self::FORMAT_1));
    }

    public function testNeedsNormalizationTraceableEncoder()
    {
        $traceableEncoder = $this->createStub(TraceableEncoder::class);
        $traceableEncoder->method('needsNormalization')->willReturn(true);
        $traceableEncoder->method('supportsEncoding')->willReturn(true);

        $this->assertTrue((new ChainEncoder([$traceableEncoder]))->needsNormalization('format'));

        $traceableEncoder = $this->createStub(TraceableEncoder::class);
        $traceableEncoder->method('needsNormalization')->willReturn(false);
        $traceableEncoder->method('supportsEncoding')->willReturn(true);

        $this->assertFalse((new ChainEncoder([$traceableEncoder]))->needsNormalization('format'));
    }

    private function createEncoder1(bool $mock = false): EncoderInterface
    {
        if ($mock) {
            $encoder = $this->createMock(ContextAwareEncoderInterface::class);
        } else {
            $encoder = $this->createStub(ContextAwareEncoderInterface::class);
        }

        $encoder
            ->method('supportsEncoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
                [self::FORMAT_3, ['foo' => 'bar2'], false],
            ]);

        return $encoder;
    }

    private function createEncoder2(bool $mock = false): EncoderInterface
    {
        if ($mock) {
            $encoder = $this->createMock(EncoderInterface::class);
        } else {
            $encoder = $this->createStub(EncoderInterface::class);
        }

        $encoder
            ->method('supportsEncoding')
            ->willReturnMap([
                [self::FORMAT_1, [], false],
                [self::FORMAT_2, [], true],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], false],
                [self::FORMAT_3, ['foo' => 'bar2'], true],
            ]);

        return $encoder;
    }
}

class NormalizationAwareEncoder implements EncoderInterface, NormalizationAwareInterface
{
    public function supportsEncoding(string $format): bool
    {
        return true;
    }

    public function encode($data, string $format, array $context = []): string
    {
    }
}
