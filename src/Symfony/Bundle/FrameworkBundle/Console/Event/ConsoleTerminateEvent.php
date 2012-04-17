<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Event;

/**
 * Allows to receive the exit code of a command after its execution.
 *
 * @author Francesco Levorato <git@flevour.net>
 */
class ConsoleTerminateEvent extends KernelEvent
{

    /**
     * The exit code of the command.
     *
     * @var integer
     */
    private $exitCode;

    function __construct($exitCode)
    {
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
