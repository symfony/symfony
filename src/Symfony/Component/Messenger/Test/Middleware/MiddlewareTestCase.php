<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Test\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
abstract class MiddlewareTestCase extends TestCase
{
    protected function getStackMock(bool $nextIsCalled = true)
    {
        $nextMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $nextMiddleware
            ->expects($nextIsCalled ? $this->once() : $this->never())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack): Envelope {
                return $envelope;
            })
        ;

        $stack = $this->createMock(StackInterface::class);
        $stack
            ->expects($nextIsCalled ? $this->once() : $this->never())
            ->method('next')
            ->willReturn($nextMiddleware)
        ;

        return $stack;
    }

    protected function getThrowingStackMock(\Throwable $throwable = null)
    {
        $nextMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willThrowException($throwable ?? new \RuntimeException('Thrown from next middleware.'))
        ;

        $stack = $this->createMock(StackInterface::class);
        $stack
            ->expects($this->once())
            ->method('next')
            ->willReturn($nextMiddleware)
        ;

        return $stack;
    }
}
