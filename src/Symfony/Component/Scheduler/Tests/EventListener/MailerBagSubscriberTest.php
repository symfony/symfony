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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Scheduler\Bag\BagRegistryInterface;
use Symfony\Component\Scheduler\Bag\MailerBag;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;
use Symfony\Component\Scheduler\EventListener\MailerBagSubscriber;
use Symfony\Component\Scheduler\EventListener\NotifierBagSubscriber;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MailerBagSubscriberTest extends TestCase
{
    public function testSubscriberListenEvents(): void
    {
        static::assertArrayHasKey(TaskToExecuteEvent::class, NotifierBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskToExecuteEvent::class, NotifierBagSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskFailedEvent::class, NotifierBagSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCannotSendMailsBeforeTaskExecutionWithoutBag(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskToExecuteEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendMailsAfterTaskExecutionWithoutBag(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskExecutedEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendMailsAfterTaskFailureWithoutBag(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willThrowException(new InvalidArgumentException('The desired bag does not exist.'));

        $event = new TaskFailedEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);

        static::expectException(InvalidArgumentException::class);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCannotSendMailsBeforeTaskExecutionWithoutMails(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MailerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCannotSendMailsAfterTaskExecutionWithoutMails(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MailerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCannotSendMailsAfterTaskFailureWithoutMails(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $bag = new MailerBag();

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $subscriber = new MailerBagSubscriber($bagRegistry);
        $subscriber->onTaskFailed($event);
    }

    public function testSubscriberCanSendMailsBeforeTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $mail = $this->createMock(RawMessage::class);

        $bag = new MailerBag([$mail]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskToExecuteEvent($task);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $subscriber = new MailerBagSubscriber($bagRegistry, $mailer);
        $subscriber->onTaskToExecute($event);
    }

    public function testSubscriberCanSendMailsAfterTaskExecution(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $mail = $this->createMock(RawMessage::class);

        $bag = new MailerBag([], [$mail]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskExecutedEvent($task);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $subscriber = new MailerBagSubscriber($bagRegistry, $mailer);
        $subscriber->onTaskExecuted($event);
    }

    public function testSubscriberCanSendMailsAfterTaskFailure(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBag')->willReturn('foo');

        $mail = $this->createMock(RawMessage::class);

        $bag = new MailerBag([], [], [$mail]);

        $bagRegistry = $this->createMock(BagRegistryInterface::class);
        $bagRegistry->expects(self::once())->method('get')->willReturn($bag);
        $event = new TaskFailedEvent($task);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $subscriber = new MailerBagSubscriber($bagRegistry, $mailer);
        $subscriber->onTaskFailed($event);
    }
}
