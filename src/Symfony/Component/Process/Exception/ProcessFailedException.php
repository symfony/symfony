<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Exception;

use Symfony\Component\Process\Process;

/**
 * Exception for failed processes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.1.0
 */
class ProcessFailedException extends RuntimeException
{
    private $process;

    /**
     * @since v2.1.0
     */
    public function __construct(Process $process)
    {
        if ($process->isSuccessful()) {
            throw new InvalidArgumentException('Expected a failed process, but the given process was successful.');
        }

        parent::__construct(
            sprintf(
                'The command "%s" failed.'."\nExit Code: %s(%s)\n\nOutput:\n================\n%s\n\nError Output:\n================\n%s",
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getExitCodeText(),
                $process->getOutput(),
                $process->getErrorOutput()
            )
        );

        $this->process = $process;
    }

    /**
     * @since v2.1.0
     */
    public function getProcess()
    {
        return $this->process;
    }
}
