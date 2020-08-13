<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessage
{
    private $task;

    /**
     * @var int|float
     */
    private $workerTimeout;

    public function __construct(TaskInterface $task, $workerTimeout = 0.5)
    {
        $this->task = $task;
        $this->workerTimeout = $workerTimeout;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    /**
     * @return int|float
     */
    public function getWorkerTimeout()
    {
        return $this->workerTimeout;
    }
}
