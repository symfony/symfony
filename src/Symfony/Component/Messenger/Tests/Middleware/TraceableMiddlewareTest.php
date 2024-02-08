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
use Symfony\Component\Messenger\Middleware\TraceableStack;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

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
            public int $calls = 0;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ++$this->calls;

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->exactly(2))->method('isStarted')->willReturn(true);

        $series = [
            [$this->matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware'],
            [$this->identicalTo('Tail on "command_bus"'), 'messenger.middleware'],
        ];

        $stopwatch->expects($this->exactly(2))
            ->method('start')
            ->willReturnCallback(function (string $name, ?string $category = null) use (&$series) {
                [$constraint, $expectedCategory] = array_shift($series);

                $constraint->evaluate($name);
                $this->assertSame($expectedCategory, $category);

                return $this->createMock(StopwatchEvent::class);
            })
        ;
        $stopwatch->expects($this->exactly(2))
            ->method('stop')
            ->willReturnCallback(function (string $name) {
                static $stopSeries = [
                    '"Symfony\Component\Messenger\Middleware\MiddlewareInterface@anonymous" on "command_bus"',
                    'Tail on "command_bus"',
                ];

                $this->assertSame(array_shift($stopSeries), $name);

                return $this->createMock(StopwatchEvent::class);
            })
        ;

        $traced = new TraceableMiddleware($stopwatch, $busId);

        $traced->handle($envelope, new StackMiddleware(new \ArrayIterator([null, $middleware])));
        $this->assertSame(1, $middleware->calls);
    }

    public function testHandleWithException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');
        $busId = 'command_bus';

        $middleware = $this->createMock(MiddlewareInterface::class);
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

    public function testHandleWhenStopwatchHasBeenReset()
    {
        $busId = 'command_bus';
        $envelope = new Envelope(new DummyMessage('Hello'));

        $stopwatch = new Stopwatch();

        $middleware = new class($stopwatch) implements MiddlewareInterface {
            public int $calls = 0;
            private Stopwatch $stopwatch;

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
        $this->assertSame(1, $middleware->calls);
    }

    public function testClonedTraceableStackUnstacksIndependently()
    {
        // import TraceableStack
        class_exists(TraceableMiddleware::class);

        $stackMiddleware = new StackMiddleware([
            null,
            $this->createMock(MiddlewareInterface::class),
            $this->createMock(MiddlewareInterface::class),
        ]);

        $stopwatch = $this->createMock(Stopwatch::class);

        $traceableStack = new TraceableStack($stackMiddleware, $stopwatch, 'command_bus', 'messenger.middleware');
        $clonedStack = clone $traceableStack;

        $traceableStackMiddleware1 = $traceableStack->next();
        $traceableStackMiddleware2 = $traceableStack->next();
        $traceableStackTail = $traceableStack->next();
        self::assertSame($stackMiddleware, $traceableStackTail);

        // unstack clonedStack independently
        $clonedStackMiddleware1 = $clonedStack->next();
        self::assertSame($traceableStackMiddleware1, $clonedStackMiddleware1);
        self::assertNotSame($traceableStackMiddleware2, $clonedStackMiddleware1);

        $clonedStackMiddleware2 = $clonedStack->next();
        self::assertSame($traceableStackMiddleware2, $clonedStackMiddleware2);

        $clonedStackTail = $clonedStack->next();
        self::assertNotSame($stackMiddleware, $clonedStackTail, 'stackMiddleware was also cloned');
    }

    public function testClonedTraceableStackUsesSameStopwatch()
    {
        // import TraceableStack
        class_exists(TraceableMiddleware::class);

        $middlewareIterable = [null, $this->createMock(MiddlewareInterface::class)];

        $stackMiddleware = new StackMiddleware($middlewareIterable);

        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects($this->exactly(2))->method('isStarted')->willReturn(true);

        $startSeries = [
            [$this->matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware'],
            [$this->identicalTo('Tail on "command_bus"'), 'messenger.middleware'],
            [$this->matches('"%sMiddlewareInterface%s" on "command_bus"'), 'messenger.middleware'],
            [$this->identicalTo('Tail on "command_bus"'), 'messenger.middleware'],
        ];
        $stopwatch->expects($this->exactly(4))
            ->method('start')
            ->willReturnCallback(function (string $name, ?string $category = null) use (&$startSeries) {
                [$constraint, $expectedCategory] = array_shift($startSeries);

                $constraint->evaluate($name);
                $this->assertSame($expectedCategory, $category);

                return $this->createMock(StopwatchEvent::class);
            })
        ;

        $stopSeries = [
            $this->matches('"%sMiddlewareInterface%s" on "command_bus"'),
            $this->matches('"%sMiddlewareInterface%s" on "command_bus"'),
        ];
        $stopwatch->expects($this->exactly(2))
            ->method('stop')
            ->willReturnCallback(function (string $name) use (&$stopSeries) {
                $constraint = array_shift($stopSeries);
                $constraint->evaluate($name);

                return $this->createMock(StopwatchEvent::class);
            })
        ;

        $traceableStack = new TraceableStack($stackMiddleware, $stopwatch, 'command_bus', 'messenger.middleware');
        $clonedStack = clone $traceableStack;

        // unstack the stacks independently
        $traceableStack->next();
        $traceableStack->next();

        $clonedStack->next();
        $clonedStack->next();
    }
}
