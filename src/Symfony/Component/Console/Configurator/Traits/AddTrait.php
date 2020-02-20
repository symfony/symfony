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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Configurator\CommandConfigurator;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
trait AddTrait
{
    /**
     * @var Application
     */
    protected $application;

    final public function add(string $name): CommandConfigurator
    {
        $this->application->add($command = new Command($name));

        return new CommandConfigurator($this->application, $command);
    }
}
