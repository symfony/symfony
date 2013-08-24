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
use Symfony\Component\EventDispatcher\Event;

/**
 * Allows to inspect input and output of a command.
 *
 * @author Francesco Levorato <git@flevour.net>
 *
 * @since v2.3.0
 */
class ConsoleEvent extends Event
{
    protected $command;

    private $input;
    private $output;

    /**
     * @since v2.3.0
     */
    public function __construct(Command $command, InputInterface $input, OutputInterface $output)
    {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * Gets the command that is executed.
     *
     * @return Command A Command instance
     *
     * @since v2.3.0
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Gets the input instance.
     *
     * @return InputInterface An InputInterface instance
     *
     * @since v2.3.0
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Gets the output instance.
     *
     * @return OutputInterface An OutputInterface instance
     *
     * @since v2.3.0
     */
    public function getOutput()
    {
        return $this->output;
    }
}
