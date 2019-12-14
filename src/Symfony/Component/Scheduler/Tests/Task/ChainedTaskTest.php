<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Task\ChainedTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskTest extends TestCase
{
    public function testTasksCanBePassedIntoAChain(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);

        $task = new ChainedTask('foo', [$task, $secondTask]);
        static::assertCount(2, $task->getTasks());
    }
}
