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

    private $chainEncoder;
    private $encoder1;
    private $encoder2;

    protected function setUp(): void
    {
        $this->encoder1 = $this->createMock(ContextAwareEncoderInterface::class);
        $this->encoder1
            ->method('supportsEncoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
                [self::FORMAT_3, ['foo' => 'bar2'], false],
            ]);

        $this->encoder2 = $this->createMock(EncoderInterface::class);
        $this->encoder2
            ->method('supportsEncoding')
            ->willReturnMap([
                [self::FORMAT_1, [], false],
                [self::FORMAT_2, [], true],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], false],
                [self::FORMAT_3, ['foo' => 'bar2'], true],
            ]);

        $this->chainEncoder = new ChainEncoder([$this->encoder1, $this->encoder2]);
    }

    public function testSupportsEncoding()
    {
        $this->encoder1
            ->method('encode')
            ->willReturn('result1');
        $this->encoder2
            ->method('encode')
            ->willReturn('result2');

        $this->assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_1));
        $this->assertEquals('result1', $this->chainEncoder->encode('', self::FORMAT_1, []));

        $this->assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_2));
        $this->assertEquals('result2', $this->chainEncoder->encode('', self::FORMAT_2, []));

        $this->assertFalse($this->chainEncoder->supportsEncoding(self::FORMAT_3));

        $this->assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar']));
        $this->assertEquals('result1', $this->chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar']));

        $this->assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar2']));
        $this->assertEquals('result2', $this->chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar2']));
    }

    public function testEncode()
    {
        $this->encoder1->expects($this->never())->method('encode');
        $this->encoder2->expects($this->once())->method('encode')->willReturn('foo:123');

        $this->assertSame('foo:123', $this->chainEncoder->encode(['foo' => 123], self::FORMAT_2));
    }

    public function testEncodeUnsupportedFormat()
    {
        $this->expectException(RuntimeException::class);
        $this->chainEncoder->encode(['foo' => 123], self::FORMAT_3);
    }

    public function testNeedsNormalizationBasic()
    {
        $this->assertTrue($this->chainEncoder->needsNormalization(self::FORMAT_1));
        $this->assertTrue($this->chainEncoder->needsNormalization(self::FORMAT_2));
    }

    public function testNeedsNormalizationNormalizationAware()
    {
        $encoder = new NormalizationAwareEncoder();
        $sut = new ChainEncoder([$encoder]);

        $this->assertFalse($sut->needsNormalization(self::FORMAT_1));
    }

    public function testNeedsNormalizationTraceableEncoder()
    {
        $traceableEncoder = $this->createMock(TraceableEncoder::class);
        $traceableEncoder->method('needsNormalization')->willReturn(true);
        $traceableEncoder->method('supportsEncoding')->willReturn(true);

        $this->assertTrue((new ChainEncoder([$traceableEncoder]))->needsNormalization('format'));

        $traceableEncoder = $this->createMock(TraceableEncoder::class);
        $traceableEncoder->method('needsNormalization')->willReturn(false);
        $traceableEncoder->method('supportsEncoding')->willReturn(true);

        $this->assertFalse((new ChainEncoder([$traceableEncoder]))->needsNormalization('format'));
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
