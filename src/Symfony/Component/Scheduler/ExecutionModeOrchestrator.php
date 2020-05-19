<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionModeOrchestrator implements ExecutionModeOrchestratorInterface
{
    public const ROUND_ROBIN = 'round_robin';
    public const DEADLINE = 'deadline';
    public const BATCH = 'batch';
    public const FIFO = 'first_in_first_out';
    public const IDLE = 'idle';
    public const NORMAL = 'normal';
    public const EXECUTION_MODES = [
        self::ROUND_ROBIN,
        self::DEADLINE,
        self::BATCH,
        self::FIFO,
        self::IDLE,
        self::NORMAL,
    ];

    private $mode;

    public function __construct(string $mode = self::FIFO)
    {
        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        if (self::FIFO !== $this->mode && !\in_array($this->mode, self::EXECUTION_MODES)) {
            throw new \InvalidArgumentException(sprintf('The given mode "%s" is not a valid one, allowed ones are: "%s"', $this->mode, implode(', ', self::EXECUTION_MODES)));
        }

        switch ($this->mode) {
            case self::NORMAL:
                return $this->sortByNice($tasks);
            case self::ROUND_ROBIN:
                return $this->sortByExecutionDuration($tasks);
            case self::DEADLINE:
                return $this->sortByDeadline($tasks);
            case self::BATCH:
                return $this->sortByBatch($tasks);
            case self::IDLE:
                return $this->sortByNegativePriority($tasks);
            default:
                return $this->sortByPriority($tasks);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    private function sortByPriority(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->get('priority') >= 0 && $task->get('priority') < $nextTask->get('priority');
        });

        return $tasks;
    }

    private function sortByExecutionDuration(array $tasks) : array
    {
        $tasks = $this->sortByPriority($tasks);

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask) {
            return $task->get('duration') >= $task->get('max_duration') && $task->get('duration') < $nextTask->get('duration');
        });

        return $tasks;
    }

    private function sortByDeadline(array $tasks): array
    {
        foreach ($tasks as $task) {
            if (null === $task->get('execution_relative_deadline') || null === $task->get('arrival_time')) {
                continue;
            }

            $absoluteDeadline = $task->get('arrival_time')->diff($task->get('execution_relative_deadline'));
            $task->set('execution_absolute_deadline', $absoluteDeadline);
        }

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->get('execution_relative_deadline') < $nextTask->get('execution_relative_deadline');
        });

        return $tasks;
    }

    private function sortByBatch(array $tasks): array
    {
        array_walk($tasks, function (TaskInterface $task): bool {
            $priority = $task->get('priority');
            $task->set('priority', --$priority);

            return $task->get('priority') < $priority;
        });

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->get('priority') < $nextTask->get('priority');
        });

        return $tasks;
    }

    private function sortByNegativePriority(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->get('priority') <= 0 && $task->get('priority') < $nextTask->get('priority');
        });

        return $tasks;
    }

    private function sortByNice(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            if ($task->get('priority') > 0 || $nextTask->get('priority') > 0) {
                return false;
            }

            return $task->get('nice') > $nextTask->get('nice');
        });

        return $tasks;
    }
}
