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
