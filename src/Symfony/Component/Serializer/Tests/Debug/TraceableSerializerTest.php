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
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class TraceableSerializerTest extends TestCase
{
    public function testForwardsToSerializer()
    {
        $serializer = $this->createMock(Serializer::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('serialized');
        $serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('data', 'type', 'format', $this->isType('array'))
            ->willReturn('deserialized');
        $serializer
            ->expects($this->once())
            ->method('normalize')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('normalized');
        $serializer
            ->expects($this->once())
            ->method('denormalize')
            ->with('data', 'type', 'format', $this->isType('array'))
            ->willReturn('denormalized');
        $serializer
            ->expects($this->once())
            ->method('encode')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('encoded');
        $serializer
            ->expects($this->once())
            ->method('decode')
            ->with('data', 'format', $this->isType('array'))
            ->willReturn('decoded');

        $traceableSerializer = new TraceableSerializer($serializer, new SerializerDataCollector());

        $this->assertSame('serialized', $traceableSerializer->serialize('data', 'format'));
        $this->assertSame('deserialized', $traceableSerializer->deserialize('data', 'type', 'format'));
        $this->assertSame('normalized', $traceableSerializer->normalize('data', 'format'));
        $this->assertSame('denormalized', $traceableSerializer->denormalize('data', 'type', 'format'));
        $this->assertSame('encoded', $traceableSerializer->encode('data', 'format'));
        $this->assertSame('decoded', $traceableSerializer->decode('data', 'format'));
    }

    public function testCollectData()
    {
        $dataCollector = $this->createMock(SerializerDataCollector::class);
        $dataCollector
            ->expects($this->once())
            ->method('collectSerialize')
            ->with($this->isType('string'), 'data', 'format', $this->isType('array'), $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectDeserialize')
            ->with($this->isType('string'), 'data', 'type', 'format', $this->isType('array'), $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectNormalize')
            ->with($this->isType('string'), 'data', 'format', $this->isType('array'), $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectDenormalize')
            ->with($this->isType('string'), 'data', 'type', 'format', $this->isType('array'), $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectEncode')
            ->with($this->isType('string'), 'data', 'format', $this->isType('array'), $this->isType('float'));
        $dataCollector
            ->expects($this->once())
            ->method('collectDecode')
            ->with($this->isType('string'), 'data', 'format', $this->isType('array'), $this->isType('float'));

        $traceableSerializer = new TraceableSerializer(new Serializer(), $dataCollector);

        $traceableSerializer->serialize('data', 'format');
        $traceableSerializer->deserialize('data', 'type', 'format');
        $traceableSerializer->normalize('data', 'format');
        $traceableSerializer->denormalize('data', 'type', 'format');
        $traceableSerializer->encode('data', 'format');
        $traceableSerializer->decode('data', 'format');
    }

    public function testAddDebugTraceIdInContext()
    {
        $serializer = $this->createMock(Serializer::class);

        foreach (['serialize', 'deserialize', 'normalize', 'denormalize', 'encode', 'decode'] as $method) {
            $serializer->method($method)->willReturnCallback(function (): string {
                $context = func_get_arg(\func_num_args() - 1);
                $this->assertIsString($context[TraceableSerializer::DEBUG_TRACE_ID]);

                return '';
            });
        }

        $traceableSerializer = new TraceableSerializer($serializer, new SerializerDataCollector());

        $traceableSerializer->serialize('data', 'format');
        $traceableSerializer->deserialize('data', 'format', 'type');
        $traceableSerializer->normalize('data', 'format');
        $traceableSerializer->denormalize('data', 'format');
        $traceableSerializer->encode('data', 'format');
        $traceableSerializer->decode('data', 'format');
    }
}

class Serializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, EncoderInterface, DecoderInterface
{
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        return 'serialized';
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        return 'deserialized';
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return 'normalized';
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return true;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        return 'denormalized';
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return true;
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        return 'encoded';
    }

    public function supportsEncoding(string $format, array $context = []): bool
    {
        return true;
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        return 'decoded';
    }

    public function supportsDecoding(string $format, array $context = []): bool
    {
        return true;
    }
}
