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

use Symfony\Component\Console\Command\Command;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTask extends AbstractTask
{
    /**
     * @var string|Command
     */
    private $command;

    /**
     * @var array
     */
    private $arguments = [];

    /**
     * @var array
     */
    private $options = [];

    public function __construct(string $name, string $command, array $defaultOptions = [], array $commandArguments = [], array $commandOptions = [])
    {
        if ('' === $command) {
            throw new \InvalidArgumentException(sprintf('The command argument must be a valid command FQCN|string, given "%s"', $command));
        }

        $this->command = $command;
        $this->arguments = $commandArguments;
        $this->options = $commandOptions;

        parent::__construct($name, array_merge($this->getOptions(), [
            'command_arguments' => $commandArguments,
            'command_options' => $commandOptions,
        ], $defaultOptions), ['command_arguments' => ['string[]', 'int[]'], 'command_options' => ['string[]', 'int[]']]);
    }

    /**
     * @return string|Command
     */
    public function getCommand()
    {
        return $this->command;
    }

    public function getCommandArguments(): array
    {
        return $this->arguments;
    }

    public function getCommandOptions(): array
    {
        return $this->options;
    }
}
