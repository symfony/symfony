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
            ->with('data', 'format', $this->isArray())
            ->willReturn('encoded');

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder
            ->expects($this->once())
            ->method('decode')
            ->with('data', 'format', $this->isArray())
            ->willReturn('decoded');

        $this->assertSame('encoded', (new TraceableEncoder($encoder, new SerializerDataCollector(), 'default'))->encode('data', 'format'));
        $this->assertSame('decoded', (new TraceableEncoder($decoder, new SerializerDataCollector(), 'default'))->decode('data', 'format'));
    }

    public function testCollectEncodingData()
    {
        $serializerName = uniqid('name', true);

        $encoder = $this->createStub(EncoderInterface::class);
        $decoder = $this->createStub(DecoderInterface::class);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector
            ->expects($this->once())
            ->method('collectEncoding')
            ->with($this->isString(), $encoder::class, $this->isFloat(), $serializerName);
        $dataCollector
            ->expects($this->once())
            ->method('collectDecoding')
            ->with($this->isString(), $decoder::class, $this->isFloat(), $serializerName);

        (new TraceableEncoder($encoder, $dataCollector, $serializerName))->encode('data', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
        (new TraceableEncoder($decoder, $dataCollector, $serializerName))->decode('data', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
    }

    public function testNotCollectEncodingDataIfNoDebugTraceId()
    {
        $encoder = $this->createStub(EncoderInterface::class);
        $decoder = $this->createStub(DecoderInterface::class);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector->expects($this->never())->method('collectEncoding');
        $dataCollector->expects($this->never())->method('collectDecoding');

        (new TraceableEncoder($encoder, $dataCollector, 'default'))->encode('data', 'format');
        (new TraceableEncoder($decoder, $dataCollector, 'default'))->decode('data', 'format');
    }

    public function testCannotEncodeIfNotEncoder()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableEncoder($this->createStub(DecoderInterface::class), new SerializerDataCollector(), 'default'))->encode('data', 'format');
    }

    public function testCannotDecodeIfNotDecoder()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableEncoder($this->createStub(EncoderInterface::class), new SerializerDataCollector(), 'default'))->decode('data', 'format');
    }

    public function testSupports()
    {
        $encoder = $this->createStub(EncoderInterface::class);
        $encoder->method('supportsEncoding')->willReturn(true);

        $decoder = $this->createStub(DecoderInterface::class);
        $decoder->method('supportsDecoding')->willReturn(true);

        $traceableEncoder = new TraceableEncoder($encoder, new SerializerDataCollector(), 'default');
        $traceableDecoder = new TraceableEncoder($decoder, new SerializerDataCollector(), 'default');

        $this->assertTrue($traceableEncoder->supportsEncoding('data'));
        $this->assertTrue($traceableDecoder->supportsDecoding('data'));
        $this->assertFalse($traceableEncoder->supportsDecoding('data'));
        $this->assertFalse($traceableDecoder->supportsEncoding('data'));
    }
}
