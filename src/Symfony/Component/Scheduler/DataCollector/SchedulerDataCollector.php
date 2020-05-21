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
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var array<string,SchedulerInterface>
     */
    private $schedulers = [];

    /**
     * @var array<string,TransportInterface>
     */
    private $transports = [];

    public function registerTransport(string $name, TransportInterface $transport): void
    {
        $this->transports[$name] = $transport;
    }

    public function registerScheduler(string $name, SchedulerInterface $scheduler): void
    {
        $this->schedulers[$name] = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        // As data can comes from Messenger, local or remote schedulers|workers, we should collect it as late as possible.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect(): void
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

        foreach ($this->transports as $name => $transport) {
            $this->data['transports'][$name] = [
                'exceptions' => $transport->getExceptionsCount(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'scheduler';
    }

    public function reset(): void
    {
        $this->data['schedulers'] = [];
        $this->data['transports'] = [];
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

    /**
     * @return array<string,SchedulerInterface>
     */
    public function getSchedulers(): array
    {
        return $this->data['schedulers'];
    }

    /**
     * @return array<string,TransportInterface>
     */
    public function getTransports(): array
    {
        return $this->data['transports'];
    }
}
