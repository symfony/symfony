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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Allows to do things before the command is executed or to change the command to execute altogether.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConsoleCommandEvent extends ConsoleEvent
{
    /**
     * Sets the command.
     *
     * @param Command $command The command to execute
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;
    }
}
