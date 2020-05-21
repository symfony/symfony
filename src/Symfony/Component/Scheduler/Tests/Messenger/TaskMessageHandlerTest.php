<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Messenger\TaskMessageHandler;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessageHandlerTest extends TestCase
{
    public function testHandlerCannotRunNotDueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute');

        $task = new ShellTask('foo', 'echo Symfony', [
            'arrival_time' => new \DateTimeImmutable(),
            'expression' => '*/45 * * * *',
        ]);

        $message = new TaskMessage($task);
        $handler = new TaskMessageHandler($worker);

        ($handler)($message);
    }

    public function testHandlerCanRunDueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $task = new ShellTask('foo', 'echo Symfony', [
            'arrival_time' => new \DateTimeImmutable(),
            'expression' => '* * * * *',
        ]);

        $message = new TaskMessage($task);
        $handler = new TaskMessageHandler($worker);

        ($handler)($message);
    }
}
