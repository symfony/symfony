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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskToExecuteEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\Task\ChainedTask;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    private $runners;
    private $tracker;
    private $eventDispatcher;
    private $failedTasks;
    private $logger;
    private $running = false;
    private $shouldStop = false;
    private $workerLock;

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(iterable $runners, TaskExecutionWatcherInterface $tracker, WorkerLockInterface $workerLock, LoggerInterface $logger = null)
    {
        $this->runners = $runners;
        $this->tracker = $tracker;
        $this->workerLock = $workerLock;
        $this->eventDispatcher = new EventDispatcher();
        $this->logger = $logger;
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(TaskInterface $task): void
    {
        $this->dispatchEvent(new WorkerStartedEvent($this));

        while (!$this->shouldStop) {
            $this->checkTaskState($task);

            foreach ($this->runners as $runner) {
                if (!$runner->support($task)) {
                    continue;
                }

                $this->handleChainedTask($task);

                $lockedTask = $this->workerLock->getLock($task);

                try {
                    if ($lockedTask->acquire() && !$this->isRunning()) {
                        $this->running = true;
                        $this->tracker->watch($task);
                        $this->handleTask($runner, $task);
                        $this->tracker->endWatch($task);
                        $lockedTask->release();
                        $this->running = false;
                        $this->dispatchEvent(new WorkerStoppedEvent($this));

                        return;
                    }
                } catch (\Error $error) {
                    $this->failedTasks->add(new FailedTask($task, $error->getMessage()));
                }
            }

            throw new UndefinedRunnerException(sprintf('No runner found for the given task "%s"', $task->getName()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    private function checkTaskState(TaskInterface $task): void
    {
        if (TaskInterface::UNSPECIFIED === $task->get('state')) {
            throw new \LogicException('The task state must be defined in order to be executed!');
        }

        if (\in_array($task->get('state'), [TaskInterface::PAUSED, TaskInterface::DISABLED])) {
            $this->log(sprintf('The following task "%s" is paused|disabled, consider enable it if it should be executed!', $task->getName()));

            return;
        }
    }

    private function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatchEvent(new TaskToExecuteEvent($task));
        $output = $runner->run($task);
        $task->set('last_execution', new \DateTimeImmutable());
        $this->dispatchEvent(new TaskExecutedEvent($task, $output));
    }

    private function handleChainedTask(TaskInterface $task): void
    {
        if (!$task instanceof ChainedTask) {
            return;
        }

        foreach ($task->getTasks() as $chainedTask) {
            $this->execute($chainedTask);
        }
    }

    private function dispatchEvent(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function log(string $message): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->info($message);
    }
}
