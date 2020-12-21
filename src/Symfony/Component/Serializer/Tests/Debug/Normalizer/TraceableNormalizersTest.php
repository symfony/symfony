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
use Symfony\Component\Serializer\Debug\SerializerActionFactory;
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
 * @covers \Symfony\Component\Serializer\Debug\SerializerActionFactory
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
    private $serializerActionFactory;

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
        $serializerActionFactory = $this->getSerializerActionFactory();

        $delegate = clone $normalizerDelegate;
        yield 'normalizer:yes' => [
            true,
            $delegate,
            new TraceableNormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $normalizerDelegate;
        yield 'normalizer:no' => [
            false,
            $delegate,
            new TraceableNormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:yes' => [
            true,
            $delegate,
            new TraceableHybridNormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:no' => [
            false,
            $delegate,
            new TraceableHybridNormalizer($delegate, $serializerActionFactory),
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
        $serializerActionFactory = $this->getSerializerActionFactory();

        $delegate = clone $denormalizerDelegate;
        yield 'denormalizer:yes' => [
            true,
            $delegate,
            new TraceableDenormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $denormalizerDelegate;
        yield 'denormalizer:no' => [
            false,
            $delegate,
            new TraceableDenormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:yes' => [
            true,
            $delegate,
            new TraceableHybridNormalizer($delegate, $serializerActionFactory),
        ];

        $delegate = clone $hybridDelegate;
        yield 'hybrid:no' => [
            false,
            $delegate,
            new TraceableHybridNormalizer($delegate, $serializerActionFactory),
        ];
    }

    public function testAwarenessDelegation(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $delegate = new TestSerializerNormalizerDenormalizerAware();
        $tracer = new TraceableHybridNormalizer($delegate, $this->serializerActionFactory);

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
    public function testCacheableSupport(bool $isCachable): void
    {
        $tracer = new TraceableHybridNormalizer(new TestCacheableNormalizer($isCachable), $this->serializerActionFactory);
        self::assertSame($isCachable, $tracer->hasCacheableSupportsMethod());
    }

    public function testCacheableSupportOnNonCacheableDelegates(): void
    {
        self::assertFalse($this->traceableNormalizer->hasCacheableSupportsMethod());
    }

    public function testCallingUnknownMethodsOnDelegates(): void
    {
        $delegate = new class() implements NormalizerInterface {
            public function normalize($object, string $format = null, array $context = [])
            {
            }

            public function supportsNormalization($data, string $format = null)
            {
                return true;
            }

            public function someAction(string $text)
            {
                return $text;
            }
        };
        $tracer = new TraceableNormalizer($delegate, $this->serializerActionFactory);
        self::assertSame('foo', $tracer->someAction('foo'));
    }

    public function testCallingUnknownButNotExistingMethodsOnDelegates(): void
    {
        $this->expectException(\LogicException::class);
        $this->traceableNormalizer->someAction('foo');
    }

    public function provideYesNo(): iterable
    {
        yield 'yes' => [true];
        yield 'no' => [false];
    }

    protected function setUp(): void
    {
        $this->normalizerDelegate = $this->createMock(NormalizerInterface::class);
        $this->denormalizerDelegate = $this->createMock(DenormalizerInterface::class);
        $this->hybridDelegate = $this->createMock(TestHybridNormalizer::class);

        $serializerActionFactory = $this->getSerializerActionFactory();

        $this->traceableNormalizer = new TraceableNormalizer($this->normalizerDelegate, $serializerActionFactory);
        $this->traceableDenormalizer = new TraceableDenormalizer($this->denormalizerDelegate,$serializerActionFactory);
        $this->traceableHybridNormalizer = new TraceableHybridNormalizer($this->hybridDelegate,$serializerActionFactory);
    }

    private function getSerializerActionFactory(): SerializerActionFactory
    {
        if (!$this->serializerActionFactory) {
            $this->serializerActionFactory = new SerializerActionFactory();
        }

        return $this->serializerActionFactory;
    }
}
