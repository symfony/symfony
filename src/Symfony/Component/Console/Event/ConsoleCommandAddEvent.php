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
use Symfony\Component\EventDispatcher\Event;

/**
 * Enables access to a command immediately after
 * is has been added to the application.
 *
 * This event is useful for decorating the command
 * definition.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class ConsoleCommandAddEvent extends Event
{
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Gets the command that is executed.
     *
     * @return Command A Command instance
     */
    public function getCommand()
    {
        return $this->command;
    }
}
