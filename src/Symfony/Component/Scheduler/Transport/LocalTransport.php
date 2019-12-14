<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LocalTransport implements TransportInterface
{
    private $options;
    private $tasks = [];
    private $orchestrator;

    public function __construct(Dsn $dsn, array $options = [])
    {
        $this->options = array_merge($dsn->getOptions(), $options);
        $this->orchestrator = new ExecutionModeOrchestrator($dsn->getOption('execution_mode') ?? ExecutionModeOrchestrator::FIFO);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        return $this->list()->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return new TaskList($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if (\array_key_exists($task->getName(), $this->tasks)) {
            throw new AlreadyScheduledTaskException(sprintf('The following task "%s" has already been scheduled!', $task->getName()));
        }

        $task->set('mode', $this->orchestrator->getMode());

        $this->tasks[$task->getName()] = $task;
        $this->tasks = $this->orchestrator->sort($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->list()->offsetSet($taskName, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->list()->get($taskName);

        if (!$task instanceof TaskInterface || TaskInterface::PAUSED === $task->get('state')) {
            return;
        }

        $task->set('state', TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->list()->get($taskName);

        if (!$task instanceof TaskInterface || TaskInterface::ENABLED === $task->get('state')) {
            return;
        }

        $task->set('state', TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $this->tasks = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
