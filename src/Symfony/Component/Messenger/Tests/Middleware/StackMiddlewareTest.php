<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class StackMiddlewareTest extends TestCase
{
    public function testClone()
    {
        $middleware1 = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware1
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack): Envelope {
                $fork = clone $stack;

                $stack->next()->handle($envelope, $stack);
                $fork->next()->handle($envelope, $fork);

                return $envelope;
            })
        ;

        $middleware2 = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware2
            ->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack): Envelope {
                return $envelope;
            })
        ;

        $bus = new MessageBus([$middleware1, $middleware2]);

        $bus->dispatch(new \stdClass());
    }
}
