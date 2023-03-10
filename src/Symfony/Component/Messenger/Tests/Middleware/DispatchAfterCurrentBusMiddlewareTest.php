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

use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class DispatchAfterCurrentBusMiddlewareTest extends TestCase
{
    public function testEventsInNewTransactionAreHandledAfterMainMessage()
    {
        $message = new DummyMessage('Hello');

        $firstEvent = new DummyEvent('First event');
        $secondEvent = new DummyEvent('Second event');
        $thirdEvent = new DummyEvent('Third event');

        $middleware = new DispatchAfterCurrentBusMiddleware();
        $handlingMiddleware = $this->createMock(MiddlewareInterface::class);

        $eventBus = new MessageBus([
            $middleware,
            $handlingMiddleware,
        ]);

        $messageBus = new MessageBus([
            $middleware,
            new DispatchingMiddleware($eventBus, [
                new Envelope($firstEvent, [new DispatchAfterCurrentBusStamp()]),
                new Envelope($secondEvent, [new DispatchAfterCurrentBusStamp()]),
                $thirdEvent, // Not in a new transaction
            ]),
            $handlingMiddleware,
        ]);

        $series = [
            // Third event is dispatch within main dispatch, but before its handling:
            $thirdEvent,
            // Then expect main dispatched message to be handled first:
            $message,
            // Then, expect events in new transaction to be handled next, in dispatched order:
            $firstEvent,
            $secondEvent,
        ];

        $handlingMiddleware->expects($this->exactly(4))
            ->method('handle')
            ->with($this->callback(function (Envelope $envelope) use (&$series) {
                return $envelope->getMessage() === array_shift($series);
            }))
            ->willReturnOnConsecutiveCalls(
                $this->willHandleMessage(),
                $this->willHandleMessage(),
                $this->willHandleMessage(),
                $this->willHandleMessage()
            );

        $messageBus->dispatch($message);
    }

    public function testThrowingEventsHandlingWontStopExecution()
    {
        $message = new DummyMessage('Hello');

        $firstEvent = new DummyEvent('First event');
        $secondEvent = new DummyEvent('Second event');

        $middleware = new DispatchAfterCurrentBusMiddleware();
        $handlingMiddleware = $this->createMock(MiddlewareInterface::class);

        $eventBus = new MessageBus([
            $middleware,
            $handlingMiddleware,
        ]);

        $messageBus = new MessageBus([
            $middleware,
            new DispatchingMiddleware($eventBus, [
                new Envelope($firstEvent, [new DispatchAfterCurrentBusStamp()]),
                new Envelope($secondEvent, [new DispatchAfterCurrentBusStamp()]),
            ]),
            $handlingMiddleware,
        ]);

        $series = [
            // Expect main dispatched message to be handled first:
            $message,
            // Then, expect events in new transaction to be handled next, in dispatched order:
            $firstEvent,
            // Next event is still handled despite the previous exception:
            $secondEvent,
        ];

        $handlingMiddleware->expects($this->exactly(3))
            ->method('handle')
            ->with($this->callback(function (Envelope $envelope) use (&$series) {
                return $envelope->getMessage() === array_shift($series);
            }))
            ->willReturnOnConsecutiveCalls(
                $this->willHandleMessage(),
                $this->throwException(new \RuntimeException('Some exception while handling first event')),
                $this->willHandleMessage()
            );

        $this->expectException(DelayedMessageHandlingException::class);
        $this->expectExceptionMessage('RuntimeException: Some exception while handling first event');

        $messageBus->dispatch($message);
    }

    public function testLongChainWithExceptions()
    {
        $command = new DummyMessage('Level 0');

        $eventL1a = new DummyEvent('Event level 1A');
        $eventL1b = new DummyEvent('Event level 1B'); // will dispatch 2 more events
        $eventL1c = new DummyEvent('Event level 1C');

        $eventL2a = new DummyEvent('Event level 2A'); // Will dispatch 1 event and throw exception
        $eventL2b = new DummyEvent('Event level 2B'); // Will dispatch 1 event

        $eventL3a = new DummyEvent('Event level 3A'); // This should never get handled.
        $eventL3b = new DummyEvent('Event level 3B');

        $middleware = new DispatchAfterCurrentBusMiddleware();
        $handlingMiddleware = $this->createMock(MiddlewareInterface::class);

        $eventBus = new MessageBus([
            $middleware,
            $handlingMiddleware,
        ]);

        // The command bus will dispatch 3 events.
        $commandBus = new MessageBus([
            $middleware,
            new DispatchingMiddleware($eventBus, [
                new Envelope($eventL1a, [new DispatchAfterCurrentBusStamp()]),
                new Envelope($eventL1b, [new DispatchAfterCurrentBusStamp()]),
                new Envelope($eventL1c, [new DispatchAfterCurrentBusStamp()]),
            ]),
            $handlingMiddleware,
        ]);

        // Handling $eventL1b will dispatch 2 more events
        $series = [
            // Expect main dispatched message to be handled first:
            $command,
            $eventL1a,
            $eventL1b,
            $eventL1c,
            // Handle $eventL2a will dispatch event and throw exception
            $eventL2a,
            // Make sure $eventL2b is handled, since it was dispatched from $eventL1b
            $eventL2b,
            // We don't handle exception L3a since L2a threw an exception.
            $eventL3b,
            // Note: $eventL3a should not be handled.
        ];

        $handlingMiddleware->expects($this->exactly(7))
            ->method('handle')
            ->with($this->callback(function (Envelope $envelope) use (&$series) {
                return $envelope->getMessage() === array_shift($series);
            }))
            ->willReturnOnConsecutiveCalls(
                $this->willHandleMessage(),
                $this->willHandleMessage(),
                $this->returnCallback(function ($envelope, StackInterface $stack) use ($eventBus, $eventL2a, $eventL2b) {
                    $envelope1 = new Envelope($eventL2a, [new DispatchAfterCurrentBusStamp()]);
                    $eventBus->dispatch($envelope1);
                    $eventBus->dispatch(new Envelope($eventL2b, [new DispatchAfterCurrentBusStamp()]));

                    return $stack->next()->handle($envelope, $stack);
                }),
                $this->willHandleMessage(),
                $this->returnCallback(function () use ($eventBus, $eventL3a) {
                    $eventBus->dispatch(new Envelope($eventL3a, [new DispatchAfterCurrentBusStamp()]));

                    throw new \RuntimeException('Some exception while handling Event level 2a');
                }),
                $this->returnCallback(function ($envelope, StackInterface $stack) use ($eventBus, $eventL3b) {
                    $eventBus->dispatch(new Envelope($eventL3b, [new DispatchAfterCurrentBusStamp()]));

                    return $stack->next()->handle($envelope, $stack);
                }),
                $this->willHandleMessage()
            );

        $this->expectException(DelayedMessageHandlingException::class);
        $this->expectExceptionMessage('RuntimeException: Some exception while handling Event level 2a');

        $commandBus->dispatch($command);
    }

    public function testHandleDelayedEventFromQueue()
    {
        $message = new DummyMessage('Hello');
        $event = new DummyEvent('Event on queue');

        $middleware = new DispatchAfterCurrentBusMiddleware();
        $commandHandlingMiddleware = $this->createMock(MiddlewareInterface::class);
        $eventHandlingMiddleware = $this->createMock(MiddlewareInterface::class);

        // This bus simulates the bus that are used when messages come back form the queue
        $messageBusAfterQueue = new MessageBus([
            // Create a new middleware
            new DispatchAfterCurrentBusMiddleware(),
            $eventHandlingMiddleware,
        ]);

        $fakePutMessageOnQueue = $this->createMock(MiddlewareInterface::class);
        $fakePutMessageOnQueue->expects($this->any())
            ->method('handle')
            ->with($this->callback(function ($envelope) use ($messageBusAfterQueue) {
                // Fake putting the message on the queue
                // Fake reading the queue
                // Now, we add the message back to a new bus.
                $messageBusAfterQueue->dispatch($envelope);

                return true;
            }))
            ->willReturnArgument(0);

        $eventBus = new MessageBus([
            $middleware,
            $fakePutMessageOnQueue,
        ]);

        $messageBus = new MessageBus([
            $middleware,
            new DispatchingMiddleware($eventBus, [
                new Envelope($event, [new DispatchAfterCurrentBusStamp()]),
            ]),
            $commandHandlingMiddleware,
        ]);

        $commandHandlingMiddleware->expects($this->once())
            ->method('handle')
            ->with($this->expectHandledMessage($message))
            ->willReturnCallback(fn ($envelope, StackInterface $stack) => $stack->next()->handle($envelope, $stack));
        $eventHandlingMiddleware->expects($this->once())
            ->method('handle')
            ->with($this->expectHandledMessage($event))
            ->willReturnCallback(fn ($envelope, StackInterface $stack) => $stack->next()->handle($envelope, $stack));
        $messageBus->dispatch($message);
    }

    public function testDispatchOutOfAnotherHandlerDispatchesAndRemoveStamp()
    {
        $event = new DummyEvent('First event');

        $middleware = new DispatchAfterCurrentBusMiddleware();
        $handlingMiddleware = $this->createMock(MiddlewareInterface::class);

        $handlingMiddleware
            ->method('handle')
            ->with($this->expectHandledMessage($event))
            ->will($this->willHandleMessage());

        $eventBus = new MessageBus([
            $middleware,
            $handlingMiddleware,
        ]);

        $enveloppe = $eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);

        self::assertNull($enveloppe->last(DispatchAfterCurrentBusStamp::class));
    }

    private function expectHandledMessage($message): Callback
    {
        return $this->callback(fn (Envelope $envelope) => $envelope->getMessage() === $message);
    }

    private function willHandleMessage(): ReturnCallback
    {
        return $this->returnCallback(fn ($envelope, StackInterface $stack) => $stack->next()->handle($envelope, $stack));
    }
}

class DummyEvent
{
    private $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

class DispatchingMiddleware implements MiddlewareInterface
{
    private $bus;
    private $messages;

    public function __construct(MessageBusInterface $bus, array $messages)
    {
        $this->bus = $bus;
        $this->messages = $messages;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        foreach ($this->messages as $event) {
            $this->bus->dispatch($event);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
