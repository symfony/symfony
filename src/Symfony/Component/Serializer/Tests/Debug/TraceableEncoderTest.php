<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Debug\TraceableEncoder;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class TraceableEncoderTest extends TestCase
{
    public function testForwardsToEncoder()
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $encoder
            ->expects($this->once())
            ->method('encode')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('encoded');

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder
            ->expects($this->once())
            ->method('decode')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('decoded');

        $this->assertSame('encoded', (new TraceableEncoder($encoder, new SerializerDataCollector()))->encode('data', 'format'));
        $this->assertSame('decoded', (new TraceableEncoder($decoder, new SerializerDataCollector()))->decode('data', 'format'));
    }

    public function testCollectEncodingData()
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $decoder = $this->createMock(DecoderInterface::class);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector
            ->expects($this->once())
            ->method('collectEncoding')
            ->with($this->isType('string'), $encoder::class, $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectDecoding')
            ->with($this->isType('string'), $decoder::class, $this->isType('float'));

        (new TraceableEncoder($encoder, $dataCollector))->encode('data', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
        (new TraceableEncoder($decoder, $dataCollector))->decode('data', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
    }

    public function testNotCollectEncodingDataIfNoDebugTraceId()
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $decoder = $this->createMock(DecoderInterface::class);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector->expects($this->never())->method('collectEncoding');
        $dataCollector->expects($this->never())->method('collectDecoding');

        (new TraceableEncoder($encoder, $dataCollector))->encode('data', 'format');
        (new TraceableEncoder($decoder, $dataCollector))->decode('data', 'format');
    }

    public function testCannotEncodeIfNotEncoder()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableEncoder($this->createMock(DecoderInterface::class), new SerializerDataCollector()))->encode('data', 'format');
    }

    public function testCannotDecodeIfNotDecoder()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableEncoder($this->createMock(EncoderInterface::class), new SerializerDataCollector()))->decode('data', 'format');
    }

    public function testSupports()
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $encoder->method('supportsEncoding')->willReturn(true);

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder->method('supportsDecoding')->willReturn(true);

        $traceableEncoder = new TraceableEncoder($encoder, new SerializerDataCollector());
        $traceableDecoder = new TraceableEncoder($decoder, new SerializerDataCollector());

        $this->assertTrue($traceableEncoder->supportsEncoding('data'));
        $this->assertTrue($traceableDecoder->supportsDecoding('data'));
        $this->assertFalse($traceableEncoder->supportsDecoding('data'));
        $this->assertFalse($traceableDecoder->supportsEncoding('data'));
    }
}
