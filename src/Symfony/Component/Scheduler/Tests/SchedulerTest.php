<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\Scheduler;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\LocalTransport;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTest extends TestCase
{
    /**
     * @throws \Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCanBeCreatedWithSpecificTimezone(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $scheduler = Scheduler::forSpecificTimezone(new \DateTimeZone('Europe/London'), $transport);

        static::assertNotNull($scheduler->getTimezone());
        static::assertInstanceOf(\DateTimeZone::class, $scheduler->getTimezone());
        static::assertSame('Europe/London', $scheduler->getTimezone()->getName());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCanBeCreatedWithEventDispatcher(): void
    {
        $eventDispatcher = new SchedulerEventDispatcherMock();
        $transport = $this->createMock(TransportInterface::class);
        $scheduler = Scheduler::forSpecificTimezone(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);

        static::assertNotNull($scheduler->getTimezone());
        static::assertInstanceOf(\DateTimeZone::class, $scheduler->getTimezone());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithoutEventDispatcher(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = Scheduler::forSpecificTimezone(new \DateTimeZone('Europe/Paris'), $transport);

        $scheduler->schedule($task);

        static::assertNotEmpty($scheduler->getTasks());
        static::assertInstanceOf(TaskListInterface::class, $scheduler->getTasks());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithEventDispatcher(TaskInterface $tasks): void
    {
        $eventDispatcher = new SchedulerEventDispatcherMock();
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = Scheduler::forSpecificTimezone(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);

        $scheduler->schedule($tasks);

        static::assertNotEmpty($scheduler->getTasks());
        static::assertInstanceOf(TaskListInterface::class, $scheduler->getTasks());

        $event = $eventDispatcher->getDispatchedEvents()[TaskScheduledEvent::class];
        static::assertInstanceOf(TaskInterface::class, $event->getTask());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithEventDispatcherAndMessageBus(TaskInterface $task): void
    {
        $messageBus = new SchedulerMessageBus();
        $eventDispatcher = new SchedulerEventDispatcherMock();
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher, $messageBus);

        $task->set('queued', true);
        $scheduler->schedule($task);

        static::assertEmpty($scheduler->getTasks());
        static::assertInstanceOf(TaskListInterface::class, $scheduler->getTasks());

        $event = $eventDispatcher->getDispatchedEvents()[TaskScheduledEvent::class];
        static::assertInstanceOf(TaskInterface::class, $event->getTask());
        static::assertCount(1, $eventDispatcher->getDispatchedEvents());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCannotBeScheduledTwice(TaskInterface $task): void
    {
        $eventDispatcher = new SchedulerEventDispatcherMock();
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);

        $scheduler->schedule($task);

        static::expectException(AlreadyScheduledTaskException::class);
        static::expectExceptionMessage(sprintf('The following task "%s" has already been scheduled!', $task->getName()));
        $scheduler->schedule($task);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedWithoutEventDispatcher(TaskInterface $tasks): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);

        $eventDispatcher->expects(self::any())->method('dispatch');

        $scheduler->schedule($tasks);
        $dueTasks = $scheduler->getDueTasks();

        static::assertNotEmpty($dueTasks);
        static::assertInstanceOf(TaskListInterface::class, $dueTasks);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $tasks): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::any())->method('dispatch');

        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);
        $scheduler->schedule($tasks);

        $dueTasks = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return null !== $task->get('timezone') && 0 === $task->get('priority');
        });

        static::assertNotEmpty($dueTasks);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduled(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::any())->method('dispatch');

        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);
        $scheduler->schedule($task);

        static::assertNotEmpty($scheduler->getTasks());

        $scheduler->unSchedule($task->getName());

        static::assertCount(0, $scheduler->getTasks());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdated(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::any())->method('dispatch');

        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);
        $scheduler->schedule($task);

        static::assertNotEmpty($scheduler->getTasks()->toArray());

        $task->set('tags', ['new_tag']);

        $scheduler->update($task->getName(), $task);

        $updatedTask = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return \in_array('new_tag', $task->get('tags'));
        });
        static::assertNotEmpty($updatedTask);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBePausedAndResumed(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::any())->method('dispatch');

        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));
        $scheduler = new Scheduler(new \DateTimeZone('Europe/Paris'), $transport, $eventDispatcher);
        $scheduler->schedule($task);

        static::assertNotEmpty($scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->get('state');
        });

        static::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->get('state');
        });

        static::assertNotEmpty($resumedTasks);
    }

    public function provideTasks(): \Generator
    {
        yield 'Cron tasks' => [
            new ShellTask('Http AbstractTask - Hello', 'echo Symfony', ['expression' => '* * * * *']),
            new ShellTask('Http AbstractTask - Test', 'echo Symfony', ['expression' => '* * * * *']),
        ];
    }
}

final class SchedulerEventDispatcherMock implements EventDispatcherInterface
{
    public $dispatchedEvents = [];

    public function dispatch($event, string $eventName = null): object
    {
        $this->dispatchedEvents[\get_class($event)] = $event;

        return $event;
    }

    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }
}

final class SchedulerMessageBus implements MessageBusInterface
{
    public function dispatch($message, array $stamps = []): Envelope
    {
        return new Envelope($message, $stamps);
    }
}
