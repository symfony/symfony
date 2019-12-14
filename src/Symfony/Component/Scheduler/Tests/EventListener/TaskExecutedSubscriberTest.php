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
use Symfony\Component\Scheduler\EventListener\TaskExecutedSubscriber;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutedSubscriberTest extends TestCase
{
    public function testCorrectEventsAreListened(): void
    {
        static::assertArrayHasKey(TaskExecutedEvent::class, TaskExecutedSubscriber::getSubscribedEvents());
    }

    public function testExecutionDateIsSet(): void
    {
        $task = new ShellTask('foo', 'echo Symfony');
        $event = new TaskExecutedEvent($task);
        $subscriber = new TaskExecutedSubscriber();

        $subscriber->onTaskExecuted($event);

        static::assertInstanceof(\DatetimeImmutable::class, $task->get('last_execution'));
    }
}
