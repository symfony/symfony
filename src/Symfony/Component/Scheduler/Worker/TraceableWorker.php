<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableWorker implements WorkerInterface
{
    private $worker;
    private $executedTasks = [];

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(TaskInterface $task): void
    {
        $this->worker->execute($task);

        $this->executedTasks[$task->getName()] = $task;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->worker->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->worker->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->worker->getFailedTasks();
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->worker->addSubscriber($subscriber);
    }

    public function getExecutedTasks(): array
    {
        return $this->executedTasks;
    }
}
