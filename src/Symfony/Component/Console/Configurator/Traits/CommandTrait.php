<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Configurator\Traits;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Configurator\ArgumentConfigurator;
use Symfony\Component\Console\Configurator\OptionConfigurator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
trait CommandTrait
{
    /**
     * @var Command
     */
    protected $command;

    /**
     * @var InputDefinition
     */
    protected $definition;

    final public function code(callable $callable): self
    {
        $this->command->setCode($callable);

        return $this;
    }

    final public function argument(string $name): ArgumentConfigurator
    {
        $definition = $this->getDefinition();
        $definition->addArgument($argument = new InputArgument($name));

        return new ArgumentConfigurator($this->application, $this->command, $definition, $argument);
    }

    final public function option(string $name, $shortcut = null): OptionConfigurator
    {
        $definition = $this->getDefinition();
        $definition->addOption($option = new InputOption($name, $shortcut));

        return new OptionConfigurator($this->application, $this->command, $definition, $option);
    }

    final public function hide(): self
    {
        $this->command->setHidden(true);

        return $this;
    }

    final public function help(string $help): self
    {
        $this->command->setHelp($help);

        return $this;
    }

    final public function processTitle(string $title): self
    {
        $this->command->setProcessTitle($title);

        return $this;
    }

    final public function description(string $description): self
    {
        $this->command->setDescription($description);

        return $this;
    }

    /**
     * @param string[] $aliases
     */
    final public function aliases(...$aliases): self
    {
        $this->command->setAliases($aliases);

        return $this;
    }

    final public function usage(string $usage): self
    {
        $this->command->addUsage($usage);

        return $this;
    }

    private function getDefinition(): InputDefinition
    {
        if (null === $this->definition) {
            $this->definition = new InputDefinition();
            $this->command->setDefinition($this->definition);
        }

        return $this->definition;
    }
}
