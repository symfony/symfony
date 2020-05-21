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
final class SingleRunTask extends AbstractTask
{
    public function __construct(string $name, TaskInterface $task, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'task' => $task,
        ]), array_merge($additionalOptions, [
            'task' => [TaskInterface::class],
        ]));
    }
}
