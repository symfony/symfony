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
        $this->assertTrue($this->normalizer->supportsNormalization(new FlattenException(), null, $this->getMessengerContext()));
        $this->assertFalse($this->normalizer->supportsNormalization(new FlattenException()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @dataProvider provideFlattenException
     */
    public function testNormalize(FlattenException $exception)
    {
        $normalized = $this->normalizer->normalize($exception, null, $this->getMessengerContext());
        $previous = null === $exception->getPrevious() ? null : $this->normalizer->normalize($exception->getPrevious());

        $this->assertSame($exception->getMessage(), $normalized['message']);
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
        $this->assertSame($exception->getStatusText(), $normalized['status_text']);
    }

    public static function provideFlattenException(): array
    {
        return [
            'instance from exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42))],
            'instance with previous exception' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42, new \Exception()))],
            'instance with headers' => [FlattenException::createFromThrowable(new \RuntimeException('foo', 42), 404, ['Foo' => 'Bar'])],
        ];
    }

    public function testSupportsDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization(null, FlattenException::class));
        $this->assertTrue($this->normalizer->supportsDenormalization(null, FlattenException::class, null, $this->getMessengerContext()));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
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

        $this->assertInstanceOf(FlattenException::class, $exception);
        $this->assertSame($normalized['message'], $exception->getMessage());
        $this->assertSame($normalized['code'], $exception->getCode());
        $this->assertSame($normalized['status'], $exception->getStatusCode());
        $this->assertSame($normalized['headers'], $exception->getHeaders());
        $this->assertSame($normalized['class'], $exception->getClass());
        $this->assertSame($normalized['file'], $exception->getFile());
        $this->assertSame($normalized['line'], $exception->getLine());
        $this->assertSame($normalized['trace'], $exception->getTrace());
        $this->assertSame($normalized['trace_as_string'], $exception->getTraceAsString());
        $this->assertSame($normalized['status_text'], $exception->getStatusText());

        $this->assertInstanceOf(FlattenException::class, $previous = $exception->getPrevious());
        $this->assertSame($normalized['previous']['message'], $previous->getMessage());
        $this->assertSame($normalized['previous']['code'], $previous->getCode());
        $this->assertSame($normalized['previous']['status_text'], $previous->getStatusText());
    }

    private function getMessengerContext(): array
    {
        return [
            Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
        ];
    }
}
