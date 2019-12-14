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
final class CommandTaskFactory implements TaskFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $options): TaskInterface
    {
        $name = $options['name'] ?? '';
        $command = $options['command'] ?? '';

        unset($options['name'], $options['command']);

        return new CommandTask($name, $command, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $type): bool
    {
        return 'command' === $type;
    }
}
