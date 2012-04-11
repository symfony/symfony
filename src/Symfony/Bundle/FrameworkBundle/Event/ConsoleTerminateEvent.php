<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Allows to receive the exit code of a command after its execution.
 *
 * @author Francesco Levorato <git@flevour.net>
 */
class ConsoleTerminateEvent extends ConsoleEvent
{
    /**
     * The exit code of the command.
     *
     * @var integer
     */
    private $exitCode;

    public function __construct(InputInterface $input, OutputInterface $output, $exitCode)
    {
        parent::__construct($input, $output);
        $this->exitCode = $exitCode;
    }

    /**
     * Returns the exit code.
     *
     * @return integer
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }
}
