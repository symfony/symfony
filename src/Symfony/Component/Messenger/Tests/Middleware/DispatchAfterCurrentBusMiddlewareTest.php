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

use PHPUnit\Framework\MockObject\MockObject;
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

        // Third event is dispatch within main dispatch, but before its handling:
        $this->expectHandledMessage($handlingMiddleware, 0, $thirdEvent);
        // Then expect main dispatched message to be handled first:
        $this->expectHandledMessage($handlingMiddleware, 1, $message);
        // Then, expect events in new transaction to be handled next, in dispatched order:
        $this->expectHandledMessage($handlingMiddleware, 2, $firstEvent);
        $this->expectHandledMessage($handlingMiddleware, 3, $secondEvent);

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

        // Expect main dispatched message to be handled first:
        $this->expectHandledMessage($handlingMiddleware, 0, $message);
        // Then, expect events in new transaction to be handled next, in dispatched order:
        $this->expectThrowingHandling($handlingMiddleware, 1, $firstEvent, new \RuntimeException('Some exception while handling first event'));
        // Next event is still handled despite the previous exception:
        $this->expectHandledMessage($handlingMiddleware, 2, $secondEvent);

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

        // Expect main dispatched message to be handled first:
        $this->expectHandledMessage($handlingMiddleware, 0, $command);

        $this->expectHandledMessage($handlingMiddleware, 1, $eventL1a);

        // Handling $eventL1b will dispatch 2 more events
        $handlingMiddleware->expects($this->at(2))->method('handle')->with($this->callback(function (Envelope $envelope) use ($eventL1b) {
            return $envelope->getMessage() === $eventL1b;
        }))->willReturnCallback(function ($envelope, StackInterface $stack) use ($eventBus, $eventL2a, $eventL2b) {
            $envelope1 = new Envelope($eventL2a, [new DispatchAfterCurrentBusStamp()]);
            $eventBus->dispatch($envelope1);
            $eventBus->dispatch(new Envelope($eventL2b, [new DispatchAfterCurrentBusStamp()]));

            return $stack->next()->handle($envelope, $stack);
        });

        $this->expectHandledMessage($handlingMiddleware, 3, $eventL1c);

        // Handle $eventL2a will dispatch event and throw exception
        $handlingMiddleware->expects($this->at(4))->method('handle')->with($this->callback(function (Envelope $envelope) use ($eventL2a) {
            return $envelope->getMessage() === $eventL2a;
        }))->willReturnCallback(function ($envelope, StackInterface $stack) use ($eventBus, $eventL3a) {
            $eventBus->dispatch(new Envelope($eventL3a, [new DispatchAfterCurrentBusStamp()]));

            throw new \RuntimeException('Some exception while handling Event level 2a');
        });

        // Make sure $eventL2b is handled, since it was dispatched from $eventL1b
        $handlingMiddleware->expects($this->at(5))->method('handle')->with($this->callback(function (Envelope $envelope) use ($eventL2b) {
            return $envelope->getMessage() === $eventL2b;
        }))->willReturnCallback(function ($envelope, StackInterface $stack) use ($eventBus, $eventL3b) {
            $eventBus->dispatch(new Envelope($eventL3b, [new DispatchAfterCurrentBusStamp()]));

            return $stack->next()->handle($envelope, $stack);
        });

        // We dont handle exception L3a since L2a threw an exception.
        $this->expectHandledMessage($handlingMiddleware, 6, $eventL3b);

        // Note: $eventL3a should not be handled.

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

        $this->expectHandledMessage($commandHandlingMiddleware, 0, $message);
        $this->expectHandledMessage($eventHandlingMiddleware, 0, $event);
        $messageBus->dispatch($message);
    }

    /**
     * @param MiddlewareInterface|MockObject $handlingMiddleware
     */
    private function expectHandledMessage(MiddlewareInterface $handlingMiddleware, int $at, $message): void
    {
        $handlingMiddleware->expects($this->at($at))->method('handle')->with($this->callback(function (Envelope $envelope) use ($message) {
            return $envelope->getMessage() === $message;
        }))->willReturnCallback(function ($envelope, StackInterface $stack) {
            return $stack->next()->handle($envelope, $stack);
        });
    }

    /**
     * @param MiddlewareInterface|MockObject $handlingMiddleware
     */
    private function expectThrowingHandling(MiddlewareInterface $handlingMiddleware, int $at, $message, \Throwable $throwable): void
    {
        $handlingMiddleware->expects($this->at($at))->method('handle')->with($this->callback(function (Envelope $envelope) use ($message) {
            return $envelope->getMessage() === $message;
        }))->willReturnCallback(function () use ($throwable) {
            throw $throwable;
        });
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
