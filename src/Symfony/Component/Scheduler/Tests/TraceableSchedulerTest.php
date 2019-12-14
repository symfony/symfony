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
use Symfony\Component\Scheduler\Scheduler;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\TraceableScheduler;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\LocalTransport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableSchedulerTest extends TestCase
{
    public function testTaskDataCanBeCollected(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('foo');
        $transport = new LocalTransport(Dsn::fromString('local://root?execution_mode=first_in_first_out'));

        $scheduler = new Scheduler(new \DateTimeZone('Europe/London'), $transport);
        $traceableScheduler = new TraceableScheduler($scheduler);

        $traceableScheduler->schedule($task);

        static::assertNotEmpty($traceableScheduler->getScheduledTasks());
    }
}
