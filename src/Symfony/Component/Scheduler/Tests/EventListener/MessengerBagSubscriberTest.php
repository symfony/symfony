<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MessengerBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\EventListener\MessengerBagSubscriber;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerBagSubscriberTest extends TestCase
{
    public function testSubscriberListenEvents(): void
    {
        static::assertArrayHasKey(TaskExecutingEvent::class, MessengerBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskExecutingEvent::class, MessengerBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskFailedEvent::class, MessengerBagSubscriber::getSubscribedEvents());
    }
    public function testSubscriberCannotSendMessagesBeforeTaskExecutionWithoutBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutingEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskExecutionWithoutBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskFailureWithoutBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskFailedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendMessagesBeforeTaskExecutionWithoutBag(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutingEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskExecutionWithoutBag(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskFailureWithoutBag(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskFailedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendMessagesBeforeTaskExecutionWithoutMessages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MessengerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutingEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskExecutionWithoutMessages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MessengerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendMessagesAfterTaskFailureWithoutMessages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MessengerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCanSendMessagesBeforeTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $message = new FooMessage();

        $bag = new MessengerBag([$message]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutingEvent($task);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCanSendMessagesAfterTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $message = new FooMessage();

        $bag = new MessengerBag([], [$message]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCanSendMessagesAfterTaskFailure(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $message = new FooMessage();

        $bag = new MessengerBag([], [], [$message]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new FooMessage()));

        $subscriber = new MessengerBagSubscriber($bagRegistry, $bus);
        $subscriber->onTaskFailed($event);
    }
}

final class FooMessage
{
}
