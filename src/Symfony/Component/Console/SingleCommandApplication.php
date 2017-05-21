<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Application providing access to just one command.
 *
 * When a console application only consists of one
 * command, having to specify this command's name as first
 * argument is superfluous.
 * This class simplifies creating and using this
 * kind of applications.
 *
 * Usage:
 *
 *     $cmd = new SimpleCommand();
 *     $app = new SingleCommandApplication($cmd, '1.2');
 *     $app->run();
 *
 * @author Stefaan Lippens <soxofaan@gmail.com>
 */
class SingleCommandApplication extends Application
{
    /**
     * Name of the single accessible command of this application
     * @var string
     */
    private $commandName;

    /**
     * Constructor to build a "single command" application, given a command.
     *
     * The application will adopt the same name as the command.
     *
     * @param Command $command The single (accessible) command for this application
     * @param string $version The version of the application
     */
    public function __construct(Command $command, $version = 'UNKNOWN')
    {
        parent::__construct($command->getName(), $version);

        // Add the given command as single (accessible) command.
        $this->add($command);
        $this->commandName = $command->getName();

        // Override the Application's definition so that it does not
        // require a command name as first argument.
        $this->getDefinition()->setArguments();
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommandName(InputInterface $input)
    {
        return $this->commandName;
    }

    /**
     * Adds a command object.
     *
     * This function overrides (public) Application::add()
     * but should should only be used internally.
     * Will raise \LogicException when called
     * after the single accessible command is set up
     * (from the constructor).
     *
     * @param Command $command A Command object
     *
     * @return Command The registered command
     *
     * @throws \LogicException
     */
    public function add(Command $command)
    {
        // Fail if we already set up the single accessible command.
        if ($this->commandName) {
            throw new \LogicException("You should not add additional commands to a SingleCommandApplication instance.");
        }

        return parent::add($command);
    }
}
