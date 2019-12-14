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
use Symfony\Component\Scheduler\Transport\NullTransport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTransportTest extends TestCase
{
    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotCreateATask(TaskInterface $task): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotUpdateATask(TaskInterface $task): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotDeleteATask(TaskInterface $task): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseATask(TaskInterface $task): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotResumeAPausedTask(TaskInterface $task): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->create($task);
    }

    public function testTransportCannotEmpty(): void
    {
        $transport = new NullTransport(Dsn::fromString('null://test'));

        static::expectException(\RuntimeException::class);
        $transport->empty();
    }

    public function provideTasks(): \Generator
    {
        yield [
            new ShellTask('ShellTask - Hello', 'echo Symfony', ['expression' => '* * * * *']),
            new ShellTask('ShellTask - Test', 'echo Symfony', ['expression' => '* * * * *']),
        ];
    }
}
