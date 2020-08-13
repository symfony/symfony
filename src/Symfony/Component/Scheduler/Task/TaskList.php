<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use ArrayIterator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskList implements TaskListInterface
{
    private $tasks = [];

    /**
     * @param TaskInterface[] $tasks
     */
    public function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->add($task);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(TaskInterface $task): void
    {
        $this->tasks[$task->getName()] = $task;
    }

    /**
     * {@inheritdoc}
     */
    public function addMultiples(array $tasks): void
    {
        foreach ($tasks as $task) {
            $this->add($task);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        return isset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): ?TaskInterface
    {
        return $this->tasks[$taskName];
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface
    {
        $tasks = [];

        foreach ($this->tasks as $task) {
            if (\in_array($task->getName(), $names, true)) {
                $tasks[] = $task;
            }
        }

        return new static($tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $filter): TaskListInterface
    {
        return new static(array_filter($this->tasks, $filter, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        if (null === $this->tasks[$taskName]) {
            return;
        }

        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof TaskInterface) {
            throw new \InvalidArgumentException('A task must be given, received %s', \gettype($value));
        }

        null === $offset ? $this->add($value) : $this->tasks[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values($this->tasks);
    }
}
