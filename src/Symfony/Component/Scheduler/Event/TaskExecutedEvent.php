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

use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutedEvent extends Event
{
    private $task;
    private $output;

    public function __construct(TaskInterface $task, Output $output = null)
    {
        $this->task = $task;
        $this->output = $output;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getOutput(): Output
    {
        return $this->output;
    }
}
