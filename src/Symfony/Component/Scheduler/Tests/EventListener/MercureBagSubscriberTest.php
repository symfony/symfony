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
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MercureBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;
use Symfony\Component\Scheduler\EventListener\MercureBagSubscriber;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureBagSubscriberTest extends TestCase
{
    public function testSubscriberListenEvents(): void
    {
        static::assertArrayHasKey(TaskToExecuteEvent::class, MercureBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskToExecuteEvent::class, MercureBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskFailedEvent::class, MercureBagSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCannotSendUpdatesBeforeTaskExecutionWithoutPublisher(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskToExecuteEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskExecutionWithoutPublisher(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskExecutedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskFailureWithoutPublisher(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskFailedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendUpdatesBeforeTaskExecutionWithoutBag(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskToExecuteEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskExecutionWithoutBag(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskFailureWithoutBag(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskFailedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendUpdatesBeforeTaskExecutionWithoutUpdates(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MercureBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskExecutionWithoutUpdates(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MercureBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendUpdatesAfterTaskFailureWithoutUpdates(): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::never())->method('__invoke');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MercureBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCanSendUpdatesBeforeTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $update = new Update('test', 'test');

        $bag = new MercureBag([$update]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::once())->method('__invoke');

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCanSendUpdatesAfterTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $update = new Update('test', 'test');

        $bag = new MercureBag([], [$update]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::once())->method('__invoke');

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCanSendUpdatesAfterTaskFailure(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $update = new Update('test', 'test');

        $bag = new MercureBag([], [], [$update]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->expects(self::once())->method('__invoke');

        $subscriber = new MercureBagSubscriber($bagRegistry, $publisher);
        $subscriber->onTaskFailed($event);
    }
}
