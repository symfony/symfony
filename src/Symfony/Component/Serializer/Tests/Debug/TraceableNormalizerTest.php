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
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Tests\Fixtures\UpcomingDenormalizerInterface as DenormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\UpcomingNormalizerInterface as NormalizerInterface;

class TraceableNormalizerTest extends TestCase
{
    public function testForwardsToNormalizer()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer
            ->expects($this->once())
            ->method('normalize')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('normalized');

        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $denormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->with('data', 'type', 'format', $this->isType('array'))
            ->willReturn('denormalized');

        $this->assertSame('normalized', (new TraceableNormalizer($normalizer, new SerializerDataCollector()))->normalize('data', 'format'));
        $this->assertSame('denormalized', (new TraceableNormalizer($denormalizer, new SerializerDataCollector()))->denormalize('data', 'type', 'format'));
    }

    public function testCollectNormalizationData()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->method('getSupportedTypes')->willReturn(['*' => false]);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector
            ->expects($this->once())
            ->method('collectNormalization')
            ->with($this->isType('string'), $normalizer::class, $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectDenormalization')
            ->with($this->isType('string'), $denormalizer::class, $this->isType('float'));

        (new TraceableNormalizer($normalizer, $dataCollector))->normalize('data', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
        (new TraceableNormalizer($denormalizer, $dataCollector))->denormalize('data', 'type', 'format', [TraceableSerializer::DEBUG_TRACE_ID => 'debug']);
    }

    public function testNotCollectNormalizationDataIfNoDebugTraceId()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->method('getSupportedTypes')->willReturn(['*' => false]);

        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector->expects($this->never())->method('collectNormalization');
        $dataCollector->expects($this->never())->method('collectDenormalization');

        (new TraceableNormalizer($normalizer, $dataCollector))->normalize('data', 'format');
        (new TraceableNormalizer($denormalizer, $dataCollector))->denormalize('data', 'type', 'format');
    }

    public function testCannotNormalizeIfNotNormalizer()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableNormalizer($this->createMock(DenormalizerInterface::class), new SerializerDataCollector()))->normalize('data');
    }

    public function testCannotDenormalizeIfNotDenormalizer()
    {
        $this->expectException(\BadMethodCallException::class);

        (new TraceableNormalizer($this->createMock(NormalizerInterface::class), new SerializerDataCollector()))->denormalize('data', 'type');
    }

    public function testSupports()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer->method('supportsNormalization')->willReturn(true);

        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $denormalizer->method('supportsDenormalization')->willReturn(true);

        $traceableNormalizer = new TraceableNormalizer($normalizer, new SerializerDataCollector());
        $traceableDenormalizer = new TraceableNormalizer($denormalizer, new SerializerDataCollector());

        $this->assertTrue($traceableNormalizer->supportsNormalization('data'));
        $this->assertTrue($traceableDenormalizer->supportsDenormalization('data', 'type'));
        $this->assertFalse($traceableNormalizer->supportsDenormalization('data', 'type'));
        $this->assertFalse($traceableDenormalizer->supportsNormalization('data'));
    }
}
