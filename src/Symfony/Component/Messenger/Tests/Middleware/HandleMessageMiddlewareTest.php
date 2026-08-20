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

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\HandlerFailureEvent;
use Symfony\Component\Messenger\Event\HandlerStartingEvent;
use Symfony\Component\Messenger\Event\HandlerSuccessEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItCallsTheHandlerAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $handler = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testItKeysTheHandlerFailedNestedExceptionsByHandlerDescription()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $handler = new class {
            public function __invoke()
            {
                throw new \Exception('failed');
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        try {
            $middleware->handle($envelope, $this->getStackMock(false));
        } catch (HandlerFailedException $e) {
            $key = (new HandlerDescriptor($handler))->getName();

            $this->assertCount(1, $e->getWrappedExceptions());
            $this->assertArrayHasKey($key, $e->getWrappedExceptions());
            $this->assertSame('failed', $e->getWrappedExceptions()[$key]->getMessage());

            return;
        }

        $this->fail('Exception not thrown.');
    }

    #[DataProvider('itAddsHandledStampsProvider')]
    public function testItAddsHandledStamps(array $handlers, array $expectedStamps, bool $nextIsCalled)
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => $handlers,
        ]));

        try {
            $envelope = $middleware->handle($envelope, $this->getStackMock($nextIsCalled));
        } catch (HandlerFailedException $e) {
            $envelope = $e->getEnvelope();
        }

        $this->assertEquals($expectedStamps, $envelope->all(HandledStamp::class));
    }

    public static function itAddsHandledStampsProvider(): iterable
    {
        $first = new class extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                return 'first result';
            }
        };
        $firstClass = $first::class;

        $second = new class extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                return null;
            }
        };
        $secondClass = $second::class;

        $failing = new class extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                throw new \Exception('handler failed.');
            }
        };

        yield 'A stamp is added' => [
            [$first],
            [new HandledStamp('first result', $firstClass.'::__invoke')],
            true,
        ];

        yield 'A stamp is added per handler' => [
            [
                new HandlerDescriptor($first, ['alias' => 'first']),
                new HandlerDescriptor($second, ['alias' => 'second']),
            ],
            [
                new HandledStamp('first result', $firstClass.'::__invoke@first'),
                new HandledStamp(null, $secondClass.'::__invoke@second'),
            ],
            true,
        ];

        yield 'It tries all handlers' => [
            [
                new HandlerDescriptor($first, ['alias' => 'first']),
                new HandlerDescriptor($failing, ['alias' => 'failing']),
                new HandlerDescriptor($second, ['alias' => 'second']),
            ],
            [
                new HandledStamp('first result', $firstClass.'::__invoke@first'),
                new HandledStamp(null, $secondClass.'::__invoke@second'),
            ],
            false,
        ];

        yield 'It ignores duplicated handler' => [
            [$first, $first],
            [
                new HandledStamp('first result', $firstClass.'::__invoke'),
            ],
            true,
        ];
    }

    public function testThrowsNoHandlerException()
    {
        $this->expectException(NoHandlerForMessageException::class);
        $this->expectExceptionMessage('No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"');
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]));

        $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware());
    }

    public function testMessageAlreadyHandled()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandleMessageMiddlewareTestCallable()],
        ]));

        $envelope = new Envelope(new DummyMessage('Hey'));

        $envelope = $middleware->handle($envelope, $this->getStackMock());
        $handledStamp = $envelope->all(HandledStamp::class);

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertSame($envelope->all(HandledStamp::class), $handledStamp);
    }

    public function testAllowNoHandlers()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]), true);

        $this->assertInstanceOf(Envelope::class, $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware()));
    }

    public function testBatchHandler()
    {
        $handler = new class implements BatchHandlerInterface {
            public array $processedMessages;

            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 2;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);

                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $ackedMessages = [];
        $ack = static function (Envelope $envelope, ?\Throwable $e = null) use (&$ackedMessages) {
            if (null !== $e) {
                throw $e;
            }
            $ackedMessages[] = $envelope->last(HandledStamp::class)->getResult();
        };

        $expectedMessages = [
            new DummyMessage('Hey'),
            new DummyMessage('Bob'),
        ];

        $envelopes = [];
        foreach ($expectedMessages as $message) {
            $envelopes[] = $middleware->handle(new Envelope($message, [new AckStamp($ack)]), new StackMiddleware());
        }

        $this->assertSame($expectedMessages, $handler->processedMessages);
        $this->assertSame($expectedMessages, $ackedMessages);

        $this->assertNotNull($envelopes[0]->last(NoAutoAckStamp::class));
        $this->assertNull($envelopes[1]->last(NoAutoAckStamp::class));
    }

    public function testBatchHandlerNoAck()
    {
        $handler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function shouldFlush()
            {
                return true;
            }

            private function process(array $jobs): void
            {
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $error = null;
        $ack = static function (Envelope $envelope, ?\Throwable $e = null) use (&$error) {
            $error = $e;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The acknowledger was not called by the "Symfony\Component\Messenger\Handler\BatchHandlerInterface@anonymous" batch handler.');

        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp($ack)]), new StackMiddleware());
    }

    public function testBatchHandlerCanBeUsedWithAckStamp()
    {
        $handler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public array $processedMessages = [];

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function shouldFlush()
            {
                return true;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);

                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $envelope = $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp(static function () {})]), new StackMiddleware());

        $this->assertNull($envelope->last(NoAutoAckStamp::class));
        $this->assertCount(1, $handler->processedMessages);
    }

    public function testBatchHandlerNoBatch()
    {
        $handler = new class implements BatchHandlerInterface {
            public array $processedMessages;

            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function shouldFlush()
            {
                return false;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);
                [$job, $ack] = array_shift($jobs);
                $ack->ack($job);
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $message = new DummyMessage('Hey');
        $middleware->handle(new Envelope($message), new StackMiddleware());

        $this->assertSame([$message], $handler->processedMessages);
    }

    public function testBatchHandlerFlushFalseDoesNotFlushPartialBatch()
    {
        $handler = new class implements BatchHandlerInterface {
            public array $processedMessages = [];

            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 3;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);

                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $ack = static function () {};

        $message = new DummyMessage('Hey');
        $envelope = $middleware->handle(new Envelope($message, [new AckStamp($ack)]), new StackMiddleware());

        $this->assertSame([], $handler->processedMessages);
        $this->assertCount(1, $envelope->all(NoAutoAckStamp::class));

        $handler->flush(false);

        $this->assertSame([], $handler->processedMessages);

        $handler->flush(true);

        $this->assertSame([$message], $handler->processedMessages);
    }

    public function testHandlerArgumentsStamp()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $envelope = $envelope->with(new HandlerArgumentsStamp(['additional argument']));

        $handler = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message, 'additional argument');

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testHandlerArgumentsStampNamedArgument()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $envelope = $envelope->with(new HandlerArgumentsStamp(['namedArgument' => 'additional named argument']));

        $handler = $this->createPartialMock(HandleMessageMiddlewareNamedArgumentTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message, 'additional named argument');

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testDispatchHandlerEvents()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $successHandler = $this->createMock(HandleMessageMiddlewareTestCallable::class);
        $successHandler->expects($this->once())->method('__invoke');

        $failureHandler = $this->createMock(HandleMessageMiddlewareTestCallable::class);
        $failureHandler->expects($this->once())->method('__invoke')->willThrowException(
            $exception = new \RuntimeException('Handler failed'),
        );

        $handlersLocator = new HandlersLocator([
            DummyMessage::class => [
                $successHandlerDescriptor = new HandlerDescriptor($successHandler, ['alias' => 'successHandler']),
                $failureHandlerDescriptor = new HandlerDescriptor($failureHandler, ['alias' => 'failureHandler']),
            ],
        ]);

        $dispatcher = new RecordingHandlerEventDispatcher();
        $middleware = new HandleMessageMiddleware($handlersLocator, dispatcher: $dispatcher);

        try {
            $middleware->handle($envelope, new StackMiddleware());
            $this->fail('The failing handler should bubble up as a HandlerFailedException.');
        } catch (HandlerFailedException) {
        }

        $events = $dispatcher->events;
        $this->assertCount(4, $events);

        $this->assertInstanceOf(HandlerStartingEvent::class, $events[0]);
        $this->assertSame($successHandlerDescriptor, $events[0]->handlerDescriptor);

        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[1]);
        $this->assertSame($successHandlerDescriptor, $events[1]->handlerDescriptor);
        $this->assertSame($successHandlerDescriptor->getName(), $events[1]->envelope->last(HandledStamp::class)->getHandlerName());

        $this->assertInstanceOf(HandlerStartingEvent::class, $events[2]);
        $this->assertSame($failureHandlerDescriptor, $events[2]->handlerDescriptor);

        $this->assertInstanceOf(HandlerFailureEvent::class, $events[3]);
        $this->assertSame($failureHandlerDescriptor, $events[3]->handlerDescriptor);
        $this->assertSame($exception, $events[3]->exception);
    }

    public function testBatchHandlerOutcomeEventsAreDispatchedWhenTheBatchIsFlushed()
    {
        $handler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 2;
            }

            private function process(array $jobs): void
            {
                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), dispatcher: $dispatcher = new RecordingHandlerEventDispatcher());

        $first = new DummyMessage('Hey');
        $second = new DummyMessage('Bob');
        $ack = static function () {};

        $middleware->handle(new Envelope($first, [new AckStamp($ack)]), new StackMiddleware());

        // the message is only queued at this point: no outcome yet
        $this->assertCount(1, $dispatcher->events);
        $this->assertInstanceOf(HandlerStartingEvent::class, $dispatcher->events[0]);

        // the second message fills the batch, which flushes both
        $middleware->handle(new Envelope($second, [new AckStamp($ack)]), new StackMiddleware());

        $events = $dispatcher->events;
        $this->assertCount(4, $events);
        $this->assertInstanceOf(HandlerStartingEvent::class, $events[1]);
        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[2]);
        $this->assertSame($first, $events[2]->envelope->getMessage());
        $this->assertSame($first, $events[2]->envelope->last(HandledStamp::class)->getResult());
        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[3]);
        $this->assertSame($second, $events[3]->envelope->getMessage());
    }

    public function testAPendingBatchDoesNotSwallowTheOutcomeOfTheNextHandler()
    {
        $batchHandler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 2;
            }

            private function process(array $jobs): void
            {
                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $syncHandler = $this->createMock(HandleMessageMiddlewareTestCallable::class);
        $syncHandler->expects($this->exactly(2))->method('__invoke');

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                $batchDescriptor = new HandlerDescriptor($batchHandler, ['alias' => 'batchHandler']),
                $syncDescriptor = new HandlerDescriptor($syncHandler, ['alias' => 'syncHandler']),
            ],
        ]), dispatcher: $dispatcher = new RecordingHandlerEventDispatcher());

        $ack = static function () {};
        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp($ack)]), new StackMiddleware());

        // the batch is still pending, but the second handler ran and must report its own outcome
        $events = $dispatcher->events;
        $this->assertCount(3, $events);
        $this->assertInstanceOf(HandlerStartingEvent::class, $events[0]);
        $this->assertSame($batchDescriptor, $events[0]->handlerDescriptor);
        $this->assertInstanceOf(HandlerStartingEvent::class, $events[1]);
        $this->assertSame($syncDescriptor, $events[1]->handlerDescriptor);
        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[2]);
        $this->assertSame($syncDescriptor, $events[2]->handlerDescriptor);

        // the second message fills the batch: both pending batch outcomes are dispatched
        $middleware->handle(new Envelope(new DummyMessage('Bob'), [new AckStamp($ack)]), new StackMiddleware());

        $outcomes = array_values(array_filter($dispatcher->events, static fn ($event) => $event instanceof HandlerSuccessEvent && $batchDescriptor === $event->handlerDescriptor));
        $this->assertCount(2, $outcomes);
    }

    public function testBatchHandlerFailureIsDispatchedOnceWithTheHandlerException()
    {
        $handler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 1;
            }

            private function process(array $jobs): void
            {
                foreach ($jobs as [$job, $ack]) {
                    $ack->nack(new \RuntimeException('nacked'));
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), dispatcher: $dispatcher = new RecordingHandlerEventDispatcher());

        try {
            $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp(static function () {})]), new StackMiddleware());
            $this->fail('The nacked batch should bubble up as a HandlerFailedException.');
        } catch (HandlerFailedException) {
        }

        $events = $dispatcher->events;
        $this->assertCount(2, $events);
        $this->assertInstanceOf(HandlerStartingEvent::class, $events[0]);
        $this->assertInstanceOf(HandlerFailureEvent::class, $events[1]);
        $this->assertSame('nacked', $events[1]->exception->getMessage());
    }

    public function testHandlerOutcomeEventIsDispatchedWhenAPreviousBatchIsFlushedMeanwhile()
    {
        $batchHandler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 2;
            }

            private function process(array $jobs): void
            {
                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $flushingHandler = static function (DummyMessage $message) use ($batchHandler) {
            $batchHandler->flush(true);
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                $batchDescriptor = new HandlerDescriptor($batchHandler, ['alias' => 'batchHandler']),
                $flushingDescriptor = new HandlerDescriptor($flushingHandler, ['alias' => 'flushingHandler']),
            ],
        ]), dispatcher: $dispatcher = new RecordingHandlerEventDispatcher());

        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp(static function () {})]), new StackMiddleware());

        $events = $dispatcher->events;
        $this->assertCount(4, $events);

        $this->assertInstanceOf(HandlerStartingEvent::class, $events[0]);
        $this->assertSame($batchDescriptor, $events[0]->handlerDescriptor);

        $this->assertInstanceOf(HandlerStartingEvent::class, $events[1]);
        $this->assertSame($flushingDescriptor, $events[1]->handlerDescriptor);

        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[2]);
        $this->assertSame($batchDescriptor, $events[2]->handlerDescriptor);

        $this->assertInstanceOf(HandlerSuccessEvent::class, $events[3]);
        $this->assertSame($flushingDescriptor, $events[3]->handlerDescriptor);
    }
}

class HandleMessageMiddlewareTestCallable
{
    public function __invoke()
    {
    }
}

class HandleMessageMiddlewareNamedArgumentTestCallable
{
    public function __invoke(object $message, $namedArgument)
    {
    }
}

class RecordingHandlerEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        return $this->events[] = $event;
    }
}
