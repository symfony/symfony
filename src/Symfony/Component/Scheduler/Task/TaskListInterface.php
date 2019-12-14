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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskListInterface extends \Countable, \ArrayAccess, \IteratorAggregate
{
    /**
     * Add a new task in the list, by default, the name of the task is used as the key.
     *
     * @param TaskInterface $task
     */
    public function add(TaskInterface $task): void;

    /**
     * Add an array of tasks, the name of the task is used as the key.
     *
     * @param array $tasks
     */
    public function addMultiples(array $tasks): void;

    /**
     * Return if the task exist in the list using its name.
     *
     * @param string $taskName
     *
     * @return bool
     */
    public function has(string $taskName): bool;

    /**
     * Return the desired task if found using its name, otherwise, null.
     *
     * @param string $taskName
     *
     * @return TaskInterface|null
     */
    public function get(string $taskName): ?TaskInterface;

    /**
     * Return a new list which contain the desired tasks using the names.
     *
     * @param array $names
     *
     * @return $this
     */
    public function findByName(array $names): self;

    /**
     * Allow to filter the list using a custom filter.
     *
     * @param \Closure $filter
     *
     * @return $this
     */
    public function filter(\Closure $filter): self;

    /**
     * Remove the task in the actual list if the name is a valid one.
     *
     * @param string $taskName
     */
    public function remove(string $taskName): void;

    /**
     * Return the list as an array (using tasks name's as keys).
     *
     * @return array
     */
    public function toArray(): array;
}
