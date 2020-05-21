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
final class ChainedTask extends AbstractTask
{
    public function __construct(string $name, array $tasks, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'tasks' => $tasks,
            'type' => 'chained',
        ]), array_merge($additionalOptions, [
            'tasks' => ['Symfony\Component\Scheduler\Task\TaskInterface[]']
        ]));
    }
}
