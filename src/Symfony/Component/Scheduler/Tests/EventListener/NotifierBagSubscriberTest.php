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
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\NotifierBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;
use Symfony\Component\Scheduler\EventListener\NotifierBagSubscriber;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierBagSubscriberTest extends TestCase
{
    public function testSubscriberListenEvents(): void
    {
        static::assertArrayHasKey(TaskToExecuteEvent::class, NotifierBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskToExecuteEvent::class, NotifierBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskFailedEvent::class, NotifierBagSubscriber::getSubscribedEvents());
    }
    public function testSubscriberCannotSendNotificationsBeforeTaskExecutionWithoutNotifier(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskToExecuteEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskExecutionWithoutNotifier(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskExecutedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskFailureWithoutNotifier(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::never())->method('get');

        $event = new TaskFailedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendNotificationsBeforeTaskExecutionWithoutBag(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskToExecuteEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskExecutionWithoutBag(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskFailureWithoutBag(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskFailedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendNotificationsBeforeTaskExecutionWithoutNotifications(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new NotifierBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskExecutionWithoutNotifications(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new NotifierBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendNotificationsAfterTaskFailureWithoutNotifications(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new NotifierBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCanSendNotificationsBeforeTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $notification = $this->createMock(Notification::class);

        $bag = new NotifierBag([$notification]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCanSendNotificationsAfterTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $notification = $this->createMock(Notification::class);

        $bag = new NotifierBag([], [$notification]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCanSendNotificationsAfterTaskFailure(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $notification = $this->createMock(Notification::class);

        $bag = new NotifierBag([], [], [$notification]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $subscriber = new NotifierBagSubscriber($bagRegistry, $notifier);
        $subscriber->onTaskFailed($event);
    }
}
