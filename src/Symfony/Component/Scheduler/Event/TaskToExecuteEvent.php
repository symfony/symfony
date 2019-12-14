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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToExecuteEvent extends Event
{
    private $task;

    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
