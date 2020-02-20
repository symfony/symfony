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

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class ConsoleConfigurator
{
    use Traits\AddTrait;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    final public function name(string $name): self
    {
        $this->application->setName($name);

        return $this;
    }

    final public function version(string $version): self
    {
        $this->application->setVersion($version);

        return $this;
    }

    final public function defaultCommand(string $command): self
    {
        $this->application->setDefaultCommand($command);

        return $this;
    }
}
