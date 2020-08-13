<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\Task\ChainedTask;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\SingleRunTask;
use Symfony\Component\Scheduler\Task\TaskExecutionWatcherInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function in_array;
use function sprintf;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    private $runners;
    private $watcher;
    private $eventDispatcher;
    private $failedTasks;
    private $logger;
    private $running = false;
    private $shouldStop = false;
    private $store;

    /**
     * @param iterable|RunnerInterface[]                      $runners
     * @param BlockingStoreInterface|PersistingStoreInterface $store
     */
    public function __construct(iterable $runners, TaskExecutionWatcherInterface $watcher, EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null, $store = null)
    {
        $this->runners = $runners;
        $this->watcher = $watcher;
        $this->store = $store;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(TaskInterface $task): void
    {
        $this->dispatch(new WorkerStartedEvent($this));

        while (!$this->shouldStop) {
            $this->checkTaskState($task);

            foreach ($this->runners as $runner) {
                if (!$runner->support($task)) {
                    continue;
                }

                $this->handleSingleRunTask($task);
                $this->handleChainedTask($task);

                $lockedTask = $this->getLock($task);

                try {
                    if ($lockedTask->acquire() && !$this->isRunning()) {
                        $this->running = true;
                        $this->watcher->watch($task);
                        $this->handleTask($runner, $task);
                        $this->watcher->endWatch($task);
                        $lockedTask->release();
                        $this->running = false;
                        $this->dispatch(new WorkerStoppedEvent($this));

                        return;
                    }
                } catch (Throwable $error) {
                    $this->watcher->endWatch($task);
                    $lockedTask->release();
                    $this->failedTasks->add(new FailedTask($task, $error->getMessage()));
                    $this->running = false;
                    $this->dispatch(new TaskFailedEvent($task));
                    $this->dispatch(new WorkerStoppedEvent($this));

                    return;
                }
            }

            throw new UndefinedRunnerException(sprintf('No runner found supporting the given task "%s"', $task->getName()));
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    private function checkTaskState(TaskInterface $task): void
    {
        if (TaskInterface::UNSPECIFIED === $task->get('state')) {
            throw new \LogicException('The task state must be defined in order to be executed!');
        }

        if (in_array($task->get('state'), [TaskInterface::PAUSED, TaskInterface::DISABLED])) {
            $this->log(sprintf('The following task "%s" is paused|disabled, consider enable it if it should be executed!', $task->getName()));

            return;
        }
    }

    private function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));
        $output = $runner->run($task);
        $task->set('last_execution', new DateTimeImmutable());
        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    private function handleSingleRunTask(TaskInterface $task): void
    {
        if (!$task instanceof SingleRunTask) {
            return;
        }

        $this->execute($task->get('task'));
    }

    private function handleChainedTask(TaskInterface $task): void
    {
        if (!$task instanceof ChainedTask) {
            return;
        }

        $tasks = $task->get('tasks');

        usort($tasks, function (TaskInterface $task, TaskInterface $secondTask): int {
            if (in_array($secondTask->getName(), $task->get('depends_on')) && in_array($task->getName(), $secondTask->get('depends_on'))) {
                throw new InvalidArgumentException('A circular reference has been detected, please check the tasks order.');
            }

            return in_array($secondTask->getName(), $task->get('depends_on')) ? -1 : 1;
        });

        foreach ($tasks as $chainedTask) {
            $this->execute($chainedTask);
        }
    }

    private function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $factory = new LockFactory($this->store);

        return $factory->createLock($task->getName());
    }

    private function dispatch(Event $event): void
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
