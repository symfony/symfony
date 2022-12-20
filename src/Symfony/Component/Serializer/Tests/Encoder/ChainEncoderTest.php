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
        $this->encoder1 = self::createMock(ContextAwareEncoderInterface::class);
        $this->encoder1
            ->method('supportsEncoding')
            ->willReturnMap([
                [self::FORMAT_1, [], true],
                [self::FORMAT_2, [], false],
                [self::FORMAT_3, [], false],
                [self::FORMAT_3, ['foo' => 'bar'], true],
                [self::FORMAT_3, ['foo' => 'bar2'], false],
            ]);

        $this->encoder2 = self::createMock(EncoderInterface::class);
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

        self::assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_1));
        self::assertEquals('result1', $this->chainEncoder->encode('', self::FORMAT_1, []));

        self::assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_2));
        self::assertEquals('result2', $this->chainEncoder->encode('', self::FORMAT_2, []));

        self::assertFalse($this->chainEncoder->supportsEncoding(self::FORMAT_3));

        self::assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar']));
        self::assertEquals('result1', $this->chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar']));

        self::assertTrue($this->chainEncoder->supportsEncoding(self::FORMAT_3, ['foo' => 'bar2']));
        self::assertEquals('result2', $this->chainEncoder->encode('', self::FORMAT_3, ['foo' => 'bar2']));
    }

    public function testEncode()
    {
        $this->encoder1->expects(self::never())->method('encode');
        $this->encoder2->expects(self::once())->method('encode')->willReturn('foo:123');

        self::assertSame('foo:123', $this->chainEncoder->encode(['foo' => 123], self::FORMAT_2));
    }

    public function testEncodeUnsupportedFormat()
    {
        self::expectException(RuntimeException::class);
        $this->chainEncoder->encode(['foo' => 123], self::FORMAT_3);
    }

    public function testNeedsNormalizationBasic()
    {
        self::assertTrue($this->chainEncoder->needsNormalization(self::FORMAT_1));
        self::assertTrue($this->chainEncoder->needsNormalization(self::FORMAT_2));
    }

    public function testNeedsNormalizationNormalizationAware()
    {
        $encoder = new NormalizationAwareEncoder();
        $sut = new ChainEncoder([$encoder]);

        self::assertFalse($sut->needsNormalization(self::FORMAT_1));
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
