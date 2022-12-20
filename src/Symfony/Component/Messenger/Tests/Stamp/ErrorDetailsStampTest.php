<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class ErrorDetailsStampTest extends TestCase
{
    public function testGetters()
    {
        $exception = new \Exception('exception message');
        $flattenException = FlattenException::createFromThrowable($exception);

        $stamp = ErrorDetailsStamp::create($exception);

        self::assertSame(\Exception::class, $stamp->getExceptionClass());
        self::assertSame('exception message', $stamp->getExceptionMessage());
        self::assertEquals($flattenException, $stamp->getFlattenException());
    }

    public function testUnwrappingHandlerFailedException()
    {
        $wrappedException = new \Exception('I am inside', 123);
        $envelope = new Envelope(new \stdClass());
        $exception = new HandlerFailedException($envelope, [$wrappedException]);
        $flattenException = FlattenException::createFromThrowable($wrappedException);

        $stamp = ErrorDetailsStamp::create($exception);

        self::assertSame(\Exception::class, $stamp->getExceptionClass());
        self::assertSame('I am inside', $stamp->getExceptionMessage());
        self::assertSame(123, $stamp->getExceptionCode());
        self::assertEquals($flattenException, $stamp->getFlattenException());
    }

    public function testDeserialization()
    {
        $exception = new \Exception('exception message');
        $stamp = ErrorDetailsStamp::create($exception);
        $serializer = new Serializer(
            new SymfonySerializer([
                new ArrayDenormalizer(),
                new FlattenExceptionNormalizer(),
                new ObjectNormalizer(),
            ], [new JsonEncoder()])
        );

        $deserializedEnvelope = $serializer->decode($serializer->encode(new Envelope(new \stdClass(), [$stamp])));

        $deserializedStamp = $deserializedEnvelope->last(ErrorDetailsStamp::class);
        self::assertInstanceOf(ErrorDetailsStamp::class, $deserializedStamp);
        self::assertEquals($stamp, $deserializedStamp);
    }
}
