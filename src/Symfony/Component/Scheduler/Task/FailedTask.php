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

use DateTimeImmutable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailedTask extends AbstractTask
{
    private $task;
    private $reason;
    private $failedAt;

    public function __construct(TaskInterface $task, string $reason, array $options = [], array $additionalOptions = [])
    {
        $this->task = $task;
        $this->reason = $reason;
        $this->failedAt = new DateTimeImmutable();

        parent::__construct(sprintf('%s.failed', $task->getName()), array_merge($task->getOptions(), $options), $additionalOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->task->getName();
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getFailedAt(): DateTimeImmutable
    {
        return $this->failedAt;
    }
}
