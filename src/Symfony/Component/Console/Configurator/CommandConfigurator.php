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

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class CommandConfigurator
{
    use Traits\CommandTrait;
    use Traits\AddTrait;

    public function __construct(Application $application, Command $command)
    {
        $this->application = $application;
        $this->command = $command;
    }
}
