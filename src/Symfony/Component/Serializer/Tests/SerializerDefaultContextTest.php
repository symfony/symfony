<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class SerializerDefaultContextTest extends TestCase
{
    public function testDefaultContextIsPassedToCustomNormalizerAndCanBeOverridden()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer->method('supportsNormalization')->willReturn(true);
        $normalizer->method('normalize')->willReturnCallback(static fn ($data, $format, $context) => $context);

        $serializer = new Serializer([$normalizer], defaultContext: ['mode' => 'default', 'shared' => true]);

        $this->assertSame(['mode' => 'default', 'shared' => true], $serializer->normalize(new \stdClass()));
        $this->assertSame(['mode' => 'explicit', 'shared' => true], $serializer->normalize(new \stdClass(), context: ['mode' => 'explicit']));
    }

    public function testDefaultContextIsPassedToCustomDenormalizerAndCanBeOverridden()
    {
        $normalizer = $this->createMock(DenormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer->method('supportsDenormalization')->willReturn(true);
        $normalizer->method('denormalize')->willReturnCallback(static fn ($data, $type, $format, $context) => $context);

        $serializer = new Serializer([$normalizer], defaultContext: ['mode' => 'default', 'shared' => true]);

        $this->assertSame(['mode' => 'default', 'shared' => true], $serializer->denormalize([], \stdClass::class));
        $this->assertSame(['mode' => 'explicit', 'shared' => true], $serializer->denormalize([], \stdClass::class, context: ['mode' => 'explicit']));
    }

    public function testDefaultContextIsPassedToCustomEncoderAndDecoder()
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $encoder->method('supportsEncoding')->willReturn(true);
        $encoder->method('encode')->willReturnCallback(static fn ($data, $format, $context) => json_encode($context));

        $decoder = $this->createMock(DecoderInterface::class);
        $decoder->method('supportsDecoding')->willReturn(true);
        $decoder->method('decode')->willReturnCallback(static fn ($data, $format, $context) => $context);

        $serializer = new Serializer(encoders: [$encoder, $decoder], defaultContext: ['mode' => 'default', 'shared' => true]);

        $this->assertSame('{"mode":"default","shared":true}', $serializer->encode([], 'custom'));
        $this->assertSame('{"mode":"explicit","shared":true}', $serializer->encode([], 'custom', ['mode' => 'explicit']));
        $this->assertSame(['mode' => 'default', 'shared' => true], $serializer->decode('', 'custom'));
        $this->assertSame(['mode' => 'explicit', 'shared' => true], $serializer->decode('', 'custom', ['mode' => 'explicit']));
    }

    public function testDefaultContextIsPassedThroughSerializationPipeline()
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer->method('supportsNormalization')->willReturn(true);
        $normalizer->method('normalize')->willReturnCallback(static fn ($data, $format, $context) => $context);

        $encoder = $this->createMock(EncoderInterface::class);
        $encoder->method('supportsEncoding')->willReturn(true);
        $encoder->method('encode')->willReturnCallback(static fn ($data, $format, $context) => json_encode(['data' => $data, 'context' => $context]));

        $serializer = new Serializer([$normalizer], [$encoder], ['mode' => 'default']);

        $this->assertSame(
            '{"data":{"mode":"explicit"},"context":{"mode":"explicit"}}',
            $serializer->serialize(new \stdClass(), 'custom', ['mode' => 'explicit'])
        );
    }
}
