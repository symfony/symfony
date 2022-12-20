<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Tests\Fixtures\MyOwnChildException;
use Symfony\Component\Messenger\Tests\Fixtures\MyOwnException;

class HandlerFailedExceptionTest extends TestCase
{
    public function testThatStringErrorCodeConvertsToInteger()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new class() extends \RuntimeException {
            public function __construct()
            {
                $this->code = 'HY000';
                $this->message = 'test';
                // no to call parent constructor, it will fail with string error code
            }
        };

        $handlerException = new HandlerFailedException($envelope, [$exception]);
        $originalException = $handlerException->getNestedExceptions()[0];

        self::assertIsInt($handlerException->getCode(), 'Exception codes must converts to int');
        self::assertSame(0, $handlerException->getCode(), 'String code (HY000) converted to int must be 0');
        self::assertIsString($originalException->getCode(), 'Original exception code still with original type (string)');
        self::assertSame($exception->getCode(), $originalException->getCode(), 'Original exception code is not modified');
    }

    public function testThatNestedExceptionClassAreFound()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new MyOwnException();

        $handlerException = new HandlerFailedException($envelope, [new \LogicException(), $exception]);
        self::assertSame([$exception], $handlerException->getNestedExceptionOfClass(MyOwnException::class));
    }

    public function testThatNestedExceptionClassAreFoundWhenUsingChildException()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new MyOwnChildException();

        $handlerException = new HandlerFailedException($envelope, [$exception]);
        self::assertSame([$exception], $handlerException->getNestedExceptionOfClass(MyOwnException::class));
    }

    public function testThatNestedExceptionClassAreNotFoundIfNotPresent()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new \LogicException();

        $handlerException = new HandlerFailedException($envelope, [$exception]);
        self::assertCount(0, $handlerException->getNestedExceptionOfClass(MyOwnException::class));
    }
}
