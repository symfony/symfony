<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\LocalTransport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LocalTransportTest extends TestCase
{
    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanCreateATask(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanUpdateATask(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());

        $task->set('tags', ['test']);

        $transport->update($task->getName(), $task);
        static::assertCount(1, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanDeleteATask(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());

        $transport->delete($task->getName());
        static::assertCount(0, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanPauseATask(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::PAUSED, $task->get('state'));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanResumeAPausedTask(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::PAUSED, $task->get('state'));

        $transport->resume($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::ENABLED, $task->get('state'));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanEmptyAList(TaskInterface $task): void
    {
        $transport = new LocalTransport(Dsn::fromString('local://first_in_first_out'));

        $transport->create($task);
        static::assertCount(1, $transport->list());

        $transport->clear();
        static::assertCount(0, $transport->list());
    }

    public function provideTasks(): \Generator
    {
        yield [
            new ShellTask('ShellTask - Hello', 'echo Symfony', ['expression' => '* * * * *']),
            new ShellTask('ShellTask - Test', 'echo Symfony', ['expression' => '* * * * *']),
        ];
    }
}
