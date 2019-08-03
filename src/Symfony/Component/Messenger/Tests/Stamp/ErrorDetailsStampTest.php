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

class ErrorDetailsStampTest extends TestCase
{
    public function testGetters(): void
    {
        $exception = new \Exception('exception message');
        $flattenException = FlattenException::createFromThrowable($exception);

        $stamp = new ErrorDetailsStamp($exception);

        $this->assertSame(\Exception::class, $stamp->getExceptionClass());
        $this->assertSame('exception message', $stamp->getExceptionMessage());
        $this->assertEquals($flattenException, $stamp->getFlattenException());
    }

    public function testUnwrappingHandlerFailedException(): void
    {
        $wrappedException = new \Exception('I am inside', 123);
        $envelope = new Envelope(new \stdClass());
        $exception = new HandlerFailedException($envelope, [$wrappedException]);
        $flattenException = FlattenException::createFromThrowable($wrappedException);

        $stamp = new ErrorDetailsStamp($exception);

        $this->assertSame(\Exception::class, $stamp->getExceptionClass());
        $this->assertSame('I am inside', $stamp->getExceptionMessage());
        $this->assertSame(123, $stamp->getExceptionCode());
        $this->assertEquals($flattenException, $stamp->getFlattenException());
    }
}
