<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Configurator;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class DefinitionConfigurator
{
    use Traits\CommandTrait;
    use Traits\AddTrait;

    protected $definition;

    public function __construct(Application $application, Command $command, InputDefinition $definition)
    {
        $this->definition = $definition;
        $this->application = $application;
        $this->command = $command;
    }

    final public function argument(string $name): ArgumentConfigurator
    {
        $this->definition->addArgument($argument = new InputArgument($name));

        return new ArgumentConfigurator($this->application, $this->command, $this->definition, $argument);
    }

    final public function option(string $name): OptionConfigurator
    {
        $this->definition->addOption($option = new InputOption($name));

        return new OptionConfigurator($this->application, $this->command, $this->definition, $option);
    }
}
