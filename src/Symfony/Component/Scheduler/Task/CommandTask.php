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
final class CommandTask extends AbstractTask
{
    public function __construct(string $name, string $command, array $defaultOptions = [], array $commandArguments = [], array $commandOptions = [])
    {
        if ('' === $command) {
            throw new \InvalidArgumentException(sprintf('The command argument must be a valid command FQCN|string, given "%s"', $command));
        }

        parent::__construct($name, array_merge($this->getOptions(), [
            'command' => $command,
            'command_arguments' => $commandArguments,
            'command_options' => $commandOptions,
            'type' => 'command',
        ], $defaultOptions), [
            'command_arguments' => ['string[]', 'int[]'],
            'command_options' => ['string[]', 'int[]']
        ]);
    }
}
