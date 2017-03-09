<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Allows to do things before the command is executed, like skipping the command or changing the input.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConsoleCommandEvent extends ConsoleEvent
{
    /**
     * The return code for skipped commands, this will also be passed into the terminate event.
     */
    const RETURN_CODE_DISABLED = 113;

    /**
     * Indicates if the command should be run or skipped.
     *
     * @var bool
     */
    private $commandShouldRun = true;

    /**
     * Disables the command, so it won't be run.
     *
     * @return bool
     */
    public function disableCommand()
    {
        return $this->commandShouldRun = false;
    }

    /**
     * Enables the command.
     *
     * @return bool
     */
    public function enableCommand()
    {
        return $this->commandShouldRun = true;
    }

    /**
     * Returns true if the command is runnable, false otherwise.
     *
     * @return bool
     */
    public function commandShouldRun()
    {
        return $this->commandShouldRun;
    }
}

/**
 * @internal
 */
final class InputDecorator implements InputInterface
{
    private $inner;
    private $overriddenArguments = array();
    private $overriddenOptions = array();

    public function __construct(InputInterface $inner)
    {
        $this->inner = $inner;
    }

    public function setArgument($key, $value)
    {
        $this->overriddenArguments[$key] = $value;

        return $this->inner->setArgument($key, $value);
    }

    public function setOption($key, $value)
    {
        $this->overriddenOptions[$key] = $value;

        return $this->inner->setOption($key, $value);
    }

    public function getOverriddenArguments()
    {
        return $this->overriddenArguments;
    }

    public function getOverriddenOptions()
    {
        return $this->overriddenOptions;
    }

    public function getFirstArgument()
    {
        return $this->inner->getFirstArgument();
    }

    public function hasParameterOption($values)
    {
        return $this->inner->hasParameterOption($values);
    }

    public function getParameterOption($values, $default = false)
    {
        return $this->inner->getParameterOption($values, $default);
    }

    public function bind(InputDefinition $definition)
    {
        return $this->inner->bind($definition);
    }

    public function validate()
    {
        return $this->inner->validate();
    }

    public function getArguments()
    {
        return $this->inner->getArguments();
    }

    public function getArgument($name)
    {
        return $this->inner->getArgument($name);
    }

    public function hasArgument($name)
    {
        return $this->inner->hasArgument($name);
    }

    public function getOptions()
    {
        return $this->inner->getOptions();
    }

    public function getOption($name)
    {
        return $this->inner->getOption($name);
    }

    public function hasOption($name)
    {
        return $this->inner->hasOption($name);
    }

    public function isInteractive()
    {
        return $this->inner->isInteractive();
    }

    public function setInteractive($interactive)
    {
        return $this->inner->setInteractive($interactive);
    }
}
