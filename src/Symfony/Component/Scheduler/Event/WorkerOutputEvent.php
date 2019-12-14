<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Event;

use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerOutputEvent extends Event
{
    private $output;
    private $task;
    private $worker;

    public function __construct(WorkerInterface $worker, TaskInterface $task, string $output)
    {
        $this->output = $output;
        $this->task = $task;
        $this->worker = $worker;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }
}
