<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware\Enhancers;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\Enhancers\TraceableMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class TraceableMiddlewareTest extends TestCase
{
    public function testHandle()
    {
        $busId = 'command_bus';
        $envelope = new Envelope($message = new DummyMessage('Hello'));

        $middleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($envelope, callable $next) {
                $next($envelope);
            }))
        ;

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($envelope)
        ;

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->once())->method('isStarted')->willReturn(true);
        $stopwatch->expects($this->exactly(2))
            ->method('start')
            ->with($this->matches('%sMiddlewareInterface%s (bus: command_bus)'), 'messenger.middleware')
        ;
        $stopwatch->expects($this->exactly(2))
            ->method('stop')
            ->with($this->matches('%sMiddlewareInterface%s (bus: command_bus)'))
        ;

        $traced = new TraceableMiddleware($middleware, $stopwatch, $busId);

        $traced->handle($envelope, $next);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Foo exception from next callable
     */
    public function testHandleWithException()
    {
        $busId = 'command_bus';
        $envelope = new Envelope($message = new DummyMessage('Hello'));

        $middleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $middleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($envelope, callable $next) {
                $next($envelope);
            }))
        ;

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \RuntimeException('Foo exception from next callable'))
        ;

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->once())->method('isStarted')->willReturn(true);
        // Start is only expected to be called once, as an exception is thrown by the next callable:
        $stopwatch->expects($this->exactly(1))
            ->method('start')
            ->with($this->matches('%sMiddlewareInterface%s (bus: command_bus)'), 'messenger.middleware')
        ;
        $stopwatch->expects($this->exactly(2))
            ->method('stop')
            ->with($this->matches('%sMiddlewareInterface%s (bus: command_bus)'))
        ;

        $traced = new TraceableMiddleware($middleware, $stopwatch, $busId);
        $traced->handle($envelope, $next);
    }
}
