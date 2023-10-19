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
use Symfony\Component\Messenger\Middleware\StackMiddleware;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class MiddlewareTestCase extends TestCase
{
    protected function getStackMock(bool $nextIsCalled = true)
    {
        if (!$nextIsCalled) {
            $stack = $this->createMock(StackInterface::class);
            $stack
                ->expects($this->never())
                ->method('next')
            ;

            return $stack;
        }

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(fn (Envelope $envelope, StackInterface $stack): Envelope => $envelope)
        ;

        return new StackMiddleware($nextMiddleware);
    }

    protected function getThrowingStackMock(\Throwable $throwable = null)
    {
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willThrowException($throwable ?? new \RuntimeException('Thrown from next middleware.'))
        ;

        return new StackMiddleware($nextMiddleware);
    }
}
