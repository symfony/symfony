<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Process\Exception;

use Symphony\Component\Process\Process;

/**
 * Exception that is thrown when a process has been signaled.
 *
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class ProcessSignaledException extends RuntimeException
{
    private $process;

    public function __construct(Process $process)
    {
        $this->process = $process;

        parent::__construct(sprintf('The process has been signaled with signal "%s".', $process->getTermSignal()));
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getSignal(): int
    {
        return $this->getProcess()->getTermSignal();
    }
}
