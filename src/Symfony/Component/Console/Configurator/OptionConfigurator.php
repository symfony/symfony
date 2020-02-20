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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class OptionConfigurator
{
    use Traits\CommandTrait;

    private $option;

    public function __construct(Application $application, Command $command, InputDefinition $definition, InputOption $option)
    {
        $this->definition = $definition;
        $this->application = $application;
        $this->command = $command;
        $this->option = $option;
    }

    final public function description(string $description): self
    {
        $this->option->setDescription($description);

        return $this;
    }

    final public function default(string $default): self
    {
        $this->option->setDefault($default);

        return $this;
    }

    final public function optional(): self
    {
        $this->option->addMode(InputOption::VALUE_OPTIONAL);

        return $this;
    }

    final public function array(): self
    {
        $this->option->addMode(InputOption::VALUE_IS_ARRAY);

        return $this;
    }

    final public function required(): self
    {
        $this->option->addMode(InputOption::VALUE_REQUIRED);

        return $this;
    }
}
