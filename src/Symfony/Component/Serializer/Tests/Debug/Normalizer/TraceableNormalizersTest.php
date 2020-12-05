<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Debug\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableDenormalizer;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableHybridNormalizer;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Normalizer\TestHybridNormalizer;

/**
 * @covers \Symfony\Component\Serializer\Debug\Normalizer\TraceableNormalizer
 * @covers \Symfony\Component\Serializer\Debug\Normalizer\TraceableDenormalizer
 * @covers \Symfony\Component\Serializer\Debug\Normalizer\TraceableHybridNormalizer
 * @covers \Symfony\Component\Serializer\Debug\Normalizer\AbstractTraceableNormalizer
 */
final class TraceableNormalizersTest extends TestCase
{
    /**
     * @var TraceableNormalizer
     */
    private $traceableNormalizer;
    /**
     * @var TraceableDenormalizer
     */
    private $traceableDenormalizer;
    /**
     * @var TraceableHybridNormalizer
     */
    private $traceableHybridNormalizer;
    private $normalizerDelegate;
    private $denormalizerDelegate;
    private $hybridDelegate;

    public function testInterfaces(): void
    {
        self::assertInstanceOf(NormalizerInterface::class, $this->traceableNormalizer);
        self::assertInstanceOf(DenormalizerInterface::class, $this->traceableDenormalizer);
        self::assertInstanceOf(NormalizerInterface::class, $this->traceableHybridNormalizer);
        self::assertInstanceOf(DenormalizerInterface::class, $this->traceableHybridNormalizer);

        self::assertInstanceOf(CacheableSupportsMethodInterface::class, $this->traceableNormalizer);
        self::assertInstanceOf(CacheableSupportsMethodInterface::class, $this->traceableDenormalizer);
        self::assertInstanceOf(CacheableSupportsMethodInterface::class, $this->traceableHybridNormalizer);
    }

    /**
     * @dataProvider provideDataForNormalizationDelegation
     */
    public function testSupportsNormalizationDelegation(bool $supports, $normalizer, $tracer): void
    {
        $something = new \stdClass();
        $format = 'json';
        $normalizer->expects(self::once())->method('supportsNormalization')->with($something, $format)->willReturn(
            $supports
        );
        self::assertSame($supports, $tracer->supportsNormalization($something, $format));
    }

    /**
     * @dataProvider provideDataForNormalizationDelegation
     */
    public function testNormalizationDelegation($_, $normalizer, $tracer): void
    {
        $something = new \stdClass();
        $format = 'json';
        $context = [];
        $somethingSerialized = '<some-serialized-thing>';

        $normalizer->expects(self::once())
            ->method('normalize')
            ->with($something, $format, $context)
            ->willReturn($somethingSerialized);

        self::assertSame(
            $somethingSerialized,
            $tracer->normalize($something, $format, $context)
        );
        self::assertCount(1, $tracer->getNormalizations());
    }

    public function provideDataForNormalizationDelegation(): iterable
    {
        $normalizerDelegate = $this->createMock(NormalizerInterface::class);
        $hybridDelegate = $this->createMock(TestHybridNormalizer::class);

        $delegate = clone $normalizerDelegate;
        yield 'normalizer:yes' => [
            true,
            $delegate,
            new TraceableNormalizer($delegate),
        ];

        $delegate = clone $normalizerDelegate;
        yield 'normalizer:no' => [
            false,
            $delegate,
            new TraceableNormalizer($delegate),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:yes' => [
            true,
            $delegate,
            new TraceableHybridNormalizer($delegate),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:no' => [
            false,
            $delegate,
            new TraceableHybridNormalizer($delegate),
        ];
    }

    /**
     * @dataProvider provideDataForDenormalizationDelegation
     */
    public function testSupportsDenormalizationDelegation(bool $supports, $denormalizer, $tracer): void
    {
        $something = '<some-serialized-thing>';
        $type = \stdClass::class;
        $format = 'json';

        $denormalizer->expects(self::once())
            ->method('supportsDenormalization')
            ->with($something, $type, $format)
            ->willReturn($supports);

        self::assertSame(
            $supports,
            $tracer->supportsDenormalization($something, $type, $format)
        );
    }

    /**
     * @dataProvider provideDataForDenormalizationDelegation
     */
    public function testDenormalizationDelegation($_, $denormalizer, $tracer): void
    {
        $something = new \stdClass();
        $type = \stdClass::class;
        $format = 'json';
        $context = [];
        $somethingSerialized = '<some-serialized-thing>';

        $denormalizer->expects(self::once())
            ->method('denormalize')
            ->with($somethingSerialized, $type, $format, $context)
            ->willReturn($something);

        self::assertSame(
            $something,
            $tracer->denormalize($somethingSerialized, $type, $format, $context)
        );
        self::assertCount(1, $tracer->getDenormalizations());
    }

    public function provideDataForDenormalizationDelegation(): iterable
    {
        $denormalizerDelegate = $this->createMock(DenormalizerInterface::class);
        $hybridDelegate = $this->createMock(TestHybridNormalizer::class);

        $delegate = clone $denormalizerDelegate;
        yield 'denormalizer:yes' => [
            true,
            $delegate,
            new TraceableDenormalizer($delegate),
        ];

        $delegate = clone $denormalizerDelegate;
        yield 'denormalizer:no' => [
            false,
            $delegate,
            new TraceableDenormalizer($delegate),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:yes' => [
            true,
            $delegate,
            new TraceableHybridNormalizer($delegate),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:no' => [
            false,
            $delegate,
            new TraceableHybridNormalizer($delegate),
        ];
    }

    public function testAwarenessDelegation(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $delegate = new TestSerializerNormalizerDenormalizerAware();
        $tracer = new TraceableHybridNormalizer($delegate);

        $tracer->setNormalizer($this->normalizerDelegate);
        $tracer->setDenormalizer($this->denormalizerDelegate);
        $tracer->setSerializer($serializer);

        self::assertSame($this->normalizerDelegate, $delegate->normalizer);
        self::assertSame($this->denormalizerDelegate, $delegate->denormalizer);
        self::assertSame($serializer, $delegate->serializer);
    }

    /**
     * @dataProvider provideYesNo
     */
    public function testCacheableSupport(bool $isCachable):void
    {
        $tracer = new TraceableHybridNormalizer(new TestCacheableNormalizer($isCachable));
        self::assertSame($isCachable, $tracer->hasCacheableSupportsMethod());
    }

    public function testCacheableSupportOnNonCacheableDelegates():void
    {
        self::assertFalse($this->traceableNormalizer->hasCacheableSupportsMethod());
    }

    public function provideYesNo():iterable
    {
        yield 'yes' => [true];
        yield 'no' => [false];
    }

    protected function setUp(): void
    {
        $this->normalizerDelegate = $this->createMock(NormalizerInterface::class);
        $this->denormalizerDelegate = $this->createMock(DenormalizerInterface::class);
        $this->hybridDelegate = $this->createMock(TestHybridNormalizer::class);

        $this->traceableNormalizer = new TraceableNormalizer($this->normalizerDelegate);
        $this->traceableDenormalizer = new TraceableDenormalizer($this->denormalizerDelegate);
        $this->traceableHybridNormalizer = new TraceableHybridNormalizer($this->hybridDelegate);
    }
}
