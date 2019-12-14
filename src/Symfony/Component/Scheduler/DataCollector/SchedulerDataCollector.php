<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $schedulers = [];
    private $workers = [];

    public function registerScheduler(string $name, SchedulerInterface $scheduler): void
    {
        $this->schedulers[$name] = $scheduler;
    }

    public function registerWorker(string $name, WorkerInterface $worker): void
    {
        $this->workers[$name] = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        // As data can comes from Messenger, local or remote schedulers|workers, we should collect it as late as possible.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $this->reset();

        foreach ($this->schedulers as $name => $scheduler) {
            $this->data['schedulers'][$name] = [
                'tasks' => $scheduler->getTasks(),
                'scheduled_tasks' => $scheduler->getScheduledTasks(),
                'due_tasks' => $scheduler->getDueTasks()->count(),
                'paused_tasks' => $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
                    return TaskInterface::PAUSED === $task->get('state');
                }),
                'timezone' => $scheduler->getTimezone()->getName(),
            ];
        }

        foreach ($this->workers as $name => $worker) {
            $this->data['workers'][$name] = [
                'executed_tasks' => $worker->getExecutedTasks(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'scheduler';
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data['schedulers'] = [];
        $this->data['workers'] = [];
    }

    public function getScheduledTasks(): array
    {
        $tasks = [];

        foreach ($this->data['schedulers'] as $scheduler => $data) {
            $tasks[$scheduler] = $data['scheduled_tasks'];
        }

        return $tasks;
    }

    public function getScheduledTasksByScheduler(string $scheduler): array
    {
        return $this->data['schedulers'][$scheduler]['scheduled_tasks'];
    }

    public function getExecutedTasks(): array
    {
        $tasks = [];

        foreach ($this->data['workers'] as $worker => $data) {
            $tasks[$worker] = $data['executed_tasks'];
        }

        return $tasks;
    }

    public function getSchedulers(): array
    {
        return $this->data['schedulers'];
    }

    public function getWorkers(): array
    {
        return $this->data['workers'];
    }
}
