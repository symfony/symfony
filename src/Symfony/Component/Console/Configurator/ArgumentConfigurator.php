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

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class ArgumentConfigurator
{
    use Traits\CommandTrait;

    private $argument;

    public function __construct(Application $application, Command $command, InputDefinition $definition, InputArgument $argument)
    {
        $this->application = $application;
        $this->command = $command;
        $this->definition = $definition;
        $this->argument = $argument;
    }

    final public function description(string $description): self
    {
        $this->argument->setDescription($description);

        return $this;
    }

    /**
     * @param string|string[]|null $default The default value
     */
    final public function default($default): self
    {
        $this->argument->setDefault($default);

        return $this;
    }

    final public function optional(): self
    {
        $this->argument->addMode(InputArgument::OPTIONAL);

        return $this;
    }

    final public function array(): self
    {
        $this->argument->addMode(InputArgument::IS_ARRAY);

        return $this;
    }

    final public function required(): self
    {
        $this->argument->addMode(InputArgument::REQUIRED);

        return $this;
    }
}
