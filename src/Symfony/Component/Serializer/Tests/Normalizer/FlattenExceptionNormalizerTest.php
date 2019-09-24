<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\FlattenExceptionNormalizer;

/**
 * @author Pascal Luna <skalpa@zetareticuli.org>
 */
class FlattenExceptionNormalizerTest extends TestCase
{
    /**
     * @var FlattenExceptionNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new FlattenExceptionNormalizer();
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new FlattenException()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @dataProvider provideFlattenException
     */
    public function testNormalize(FlattenException $exception): void
    {
        $normalized = $this->normalizer->normalize($exception);
        $previous = null === $exception->getPrevious() ? null : $this->normalizer->normalize($exception->getPrevious());

        $this->assertSame($exception->getMessage(), $normalized['detail']);
        $this->assertSame($exception->getCode(), $normalized['code']);
        if (null !== $exception->getStatusCode()) {
            $this->assertSame($exception->getStatusCode(), $normalized['status']);
        } else {
            $this->assertArrayNotHasKey('status', $normalized);
        }
        $this->assertSame($exception->getHeaders(), $normalized['headers']);
        $this->assertSame($exception->getClass(), $normalized['class']);
        $this->assertSame($exception->getFile(), $normalized['file']);
        $this->assertSame($exception->getLine(), $normalized['line']);
        $this->assertSame($previous, $normalized['previous']);
        $this->assertSame($exception->getTrace(), $normalized['trace']);
        $this->assertSame($exception->getTraceAsString(), $normalized['trace_as_string']);
    }

    public function provideFlattenException(): array
    {
        return [
            'instance from constructor' => [new FlattenException()],
            'instance from exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42))],
            'instance with previous exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42, new \Exception()))],
            'instance with headers' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42), 404, ['Foo' => 'Bar'])],
        ];
    }

    public function testNormalizeBadObjectTypeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsDenormalization(null, FlattenException::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalizeValidData(): void
    {
        $normalized = [];
        $exception = $this->normalizer->denormalize($normalized, FlattenException::class);

        $this->assertInstanceOf(FlattenException::class, $exception);
        $this->assertNull($exception->getMessage());
        $this->assertNull($exception->getCode());
        $this->assertNull($exception->getStatusCode());
        $this->assertNull($exception->getHeaders());
        $this->assertNull($exception->getClass());
        $this->assertNull($exception->getFile());
        $this->assertNull($exception->getLine());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getTrace());
        $this->assertNull($exception->getTraceAsString());

        $normalized = [
            'detail' => 'Something went foobar.',
            'code' => 42,
            'status' => 404,
            'headers' => ['Content-Type' => 'application/json'],
            'class' => \get_class($this),
            'file' => 'foo.php',
            'line' => 123,
            'previous' => [
                'detail' => 'Previous exception',
                'code' => 0,
            ],
            'trace' => [
                [
                    'namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => 'foo.php', 'line' => 123, 'args' => [],
                ],
            ],
            'trace_as_string' => '#0 foo.php(123): foo()'.PHP_EOL.'#1 bar.php(456): bar()',
        ];
        $exception = $this->normalizer->denormalize($normalized, FlattenException::class);

        $this->assertInstanceOf(FlattenException::class, $exception);
        $this->assertSame($normalized['detail'], $exception->getMessage());
        $this->assertSame($normalized['code'], $exception->getCode());
        $this->assertSame($normalized['status'], $exception->getStatusCode());
        $this->assertSame($normalized['headers'], $exception->getHeaders());
        $this->assertSame($normalized['class'], $exception->getClass());
        $this->assertSame($normalized['file'], $exception->getFile());
        $this->assertSame($normalized['line'], $exception->getLine());
        $this->assertSame($normalized['trace'], $exception->getTrace());
        $this->assertSame($normalized['trace_as_string'], $exception->getTraceAsString());

        $this->assertInstanceOf(FlattenException::class, $previous = $exception->getPrevious());
        $this->assertSame($normalized['previous']['detail'], $previous->getMessage());
        $this->assertSame($normalized['previous']['code'], $previous->getCode());
    }

    /**
     * @dataProvider provideInvalidNormalizedData
     */
    public function testDenormalizeInvalidDataThrowsException($normalized): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize($normalized, FlattenException::class);
    }

    public function provideInvalidNormalizedData(): array
    {
        return [
            'null' => [null],
            'string' => ['foo'],
            'integer' => [42],
            'object' => [new \stdClass()],
        ];
    }
}
