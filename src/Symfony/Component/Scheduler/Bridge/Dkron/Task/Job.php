<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Dkron\Task;

use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Job extends AbstractTask
{
    public function __construct(string $name, TaskInterface $task, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'task' => $task,
            'displayName' => $name,
        ]), array_merge($additionalOptions, [
            'displayName' => ['string'],
            'task' => [TaskInterface::class],
        ]));
    }

    public static function wrap(TaskInterface $task): self
    {
    }
}
