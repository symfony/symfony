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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Middleware\TraceableMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class TraceableMiddlewareTest extends MiddlewareTestCase
{
    public function testHandle()
    {
        $busId = 'command_bus';
        $envelope = new Envelope(new DummyMessage('Hello'));

        $middleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(function ($envelope, StackInterface $stack) {
                return $stack->next()->handle($envelope, $stack);
            })
        ;

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->once())->method('isStarted')->willReturn(true);
        $stopwatch->expects($this->exactly(2))
            ->method('start')
            ->withConsecutive(
                [$this->matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware'],
                ['Tail on "command_bus"', 'messenger.middleware']
            )
        ;
        $stopwatch->expects($this->exactly(2))
            ->method('stop')
            ->withConsecutive(
                [$this->matches('"%sMiddlewareInterface%s" on "command_bus"')],
                ['Tail on "command_bus"']
            )
        ;

        $traced = new TraceableMiddleware($stopwatch, $busId);

        $traced->handle($envelope, new StackMiddleware(new \ArrayIterator([null, $middleware])));
    }

    public function testHandleWithException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Thrown from next middleware.');
        $busId = 'command_bus';

        $middleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware->expects($this->once())
            ->method('handle')
            ->willThrowException(new \RuntimeException('Thrown from next middleware.'))
        ;

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->once())->method('isStarted')->willReturn(true);
        // Start/stop are expected to be called once, as an exception is thrown by the next callable
        $stopwatch->expects($this->once())
            ->method('start')
            ->with($this->matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware')
        ;
        $stopwatch->expects($this->once())
            ->method('stop')
            ->with($this->matches('"%sMiddlewareInterface%s" on "command_bus"'))
        ;

        $traced = new TraceableMiddleware($stopwatch, $busId);
        $traced->handle(new Envelope(new DummyMessage('Hello')), new StackMiddleware(new \ArrayIterator([null, $middleware])));
    }
}
