<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

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

    public function testSupportsNormalization()
    {
        self::assertTrue($this->normalizer->supportsNormalization(new FlattenException(), null, $this->getMessengerContext()));
        self::assertFalse($this->normalizer->supportsNormalization(new FlattenException()));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @dataProvider provideFlattenException
     */
    public function testNormalize(FlattenException $exception)
    {
        $normalized = $this->normalizer->normalize($exception, null, $this->getMessengerContext());
        $previous = null === $exception->getPrevious() ? null : $this->normalizer->normalize($exception->getPrevious());

        self::assertSame($exception->getMessage(), $normalized['message']);
        self::assertSame($exception->getCode(), $normalized['code']);
        if (null !== $exception->getStatusCode()) {
            self::assertSame($exception->getStatusCode(), $normalized['status']);
        } else {
            self::assertArrayNotHasKey('status', $normalized);
        }
        self::assertSame($exception->getHeaders(), $normalized['headers']);
        self::assertSame($exception->getClass(), $normalized['class']);
        self::assertSame($exception->getFile(), $normalized['file']);
        self::assertSame($exception->getLine(), $normalized['line']);
        self::assertSame($previous, $normalized['previous']);
        self::assertSame($exception->getTrace(), $normalized['trace']);
        self::assertSame($exception->getTraceAsString(), $normalized['trace_as_string']);
        self::assertSame($exception->getStatusText(), $normalized['status_text']);
    }

    public function provideFlattenException(): array
    {
        return [
            'instance from exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42))],
            'instance with previous exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42, new \Exception()))],
            'instance with headers' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42), 404, ['Foo' => 'Bar'])],
        ];
    }

    public function testSupportsDenormalization()
    {
        self::assertFalse($this->normalizer->supportsDenormalization(null, FlattenException::class));
        self::assertTrue($this->normalizer->supportsDenormalization(null, FlattenException::class, null, $this->getMessengerContext()));
        self::assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalizeValidData()
    {
        $normalized = [
            'message' => 'Something went foobar.',
            'code' => 42,
            'status' => 404,
            'headers' => ['Content-Type' => 'application/json'],
            'class' => static::class,
            'file' => 'foo.php',
            'line' => 123,
            'previous' => [
                'message' => 'Previous exception',
                'code' => 0,
                'class' => FlattenException::class,
                'file' => 'foo.php',
                'line' => 123,
                'headers' => ['Content-Type' => 'application/json'],
                'status_text' => 'Whoops, looks like something went wrong.',
                'trace' => [
                    [
                        'namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => 'foo.php', 'line' => 123, 'args' => [],
                    ],
                ],
                'trace_as_string' => '#0 foo.php(123): foo()'.\PHP_EOL.'#1 bar.php(456): bar()',
            ],
            'trace' => [
                [
                    'namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => 'foo.php', 'line' => 123, 'args' => [],
                ],
            ],
            'trace_as_string' => '#0 foo.php(123): foo()'.\PHP_EOL.'#1 bar.php(456): bar()',
            'status_text' => 'Whoops, looks like something went wrong.',
        ];
        $exception = $this->normalizer->denormalize($normalized, FlattenException::class);

        self::assertInstanceOf(FlattenException::class, $exception);
        self::assertSame($normalized['message'], $exception->getMessage());
        self::assertSame($normalized['code'], $exception->getCode());
        self::assertSame($normalized['status'], $exception->getStatusCode());
        self::assertSame($normalized['headers'], $exception->getHeaders());
        self::assertSame($normalized['class'], $exception->getClass());
        self::assertSame($normalized['file'], $exception->getFile());
        self::assertSame($normalized['line'], $exception->getLine());
        self::assertSame($normalized['trace'], $exception->getTrace());
        self::assertSame($normalized['trace_as_string'], $exception->getTraceAsString());
        self::assertSame($normalized['status_text'], $exception->getStatusText());

        self::assertInstanceOf(FlattenException::class, $previous = $exception->getPrevious());
        self::assertSame($normalized['previous']['message'], $previous->getMessage());
        self::assertSame($normalized['previous']['code'], $previous->getCode());
        self::assertSame($normalized['previous']['status_text'], $previous->getStatusText());
    }

    private function getMessengerContext(): array
    {
        return [
            Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
        ];
    }
}
