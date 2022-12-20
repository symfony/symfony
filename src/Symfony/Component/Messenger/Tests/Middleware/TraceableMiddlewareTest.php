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

        $middleware = new class() implements MiddlewareInterface {
            public $calls = 0;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ++$this->calls;

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $stopwatch = self::createMock(Stopwatch::class);
        $stopwatch->expects(self::exactly(2))->method('isStarted')->willReturn(true);
        $stopwatch->expects(self::exactly(2))
            ->method('start')
            ->withConsecutive(
                [self::matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware'],
                ['Tail on "command_bus"', 'messenger.middleware']
            )
        ;
        $stopwatch->expects(self::exactly(2))
            ->method('stop')
            ->withConsecutive(
                ['"Symfony\Component\Messenger\Middleware\MiddlewareInterface@anonymous" on "command_bus"'],
                ['Tail on "command_bus"']
            )
        ;

        $traced = new TraceableMiddleware($stopwatch, $busId);

        $traced->handle($envelope, new StackMiddleware(new \ArrayIterator([null, $middleware])));
        self::assertSame(1, $middleware->calls);
    }

    public function testHandleWithException()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Thrown from next middleware.');
        $busId = 'command_bus';

        $middleware = self::createMock(MiddlewareInterface::class);
        $middleware->expects(self::once())
            ->method('handle')
            ->willThrowException(new \RuntimeException('Thrown from next middleware.'))
        ;

        $stopwatch = self::createMock(Stopwatch::class);
        $stopwatch->expects(self::once())->method('isStarted')->willReturn(true);
        // Start/stop are expected to be called once, as an exception is thrown by the next callable
        $stopwatch->expects(self::once())
            ->method('start')
            ->with(self::matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware')
        ;
        $stopwatch->expects(self::once())
            ->method('stop')
            ->with(self::matches('"%sMiddlewareInterface%s" on "command_bus"'))
        ;

        $traced = new TraceableMiddleware($stopwatch, $busId);
        $traced->handle(new Envelope(new DummyMessage('Hello')), new StackMiddleware(new \ArrayIterator([null, $middleware])));
    }

    public function testHandleWhenStopwatchHasBeenReset()
    {
        $busId = 'command_bus';
        $envelope = new Envelope(new DummyMessage('Hello'));

        $stopwatch = new Stopwatch();

        $middleware = new class($stopwatch) implements MiddlewareInterface {
            public $calls = 0;
            private $stopwatch;

            public function __construct(Stopwatch $stopwatch)
            {
                $this->stopwatch = $stopwatch;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->stopwatch->reset();

                ++$this->calls;

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $traced = new TraceableMiddleware($stopwatch, $busId);

        $traced->handle($envelope, new StackMiddleware(new \ArrayIterator([null, $middleware])));
        self::assertSame(1, $middleware->calls);
    }
}
