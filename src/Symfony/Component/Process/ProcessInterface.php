<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\RuntimeException;

interface ProcessInterface extends ProcessableInterface
{
    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;

    /**
     * Returns the Pid (process identifier), if applicable.
     *
     * @return integer|null The process id if running, null otherwise
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     */
    public function getPid();

    /**
     * Returns the current output of the process (STDOUT).
     *
     * @return string The process output
     *
     * @api
     */
    public function getOutput();

    /**
     * Returns the current error output of the process (STDERR).
     *
     * @return string The process error output
     *
     * @api
     */
    public function getErrorOutput();

    /**
     * Returns the exit code returned by the process.
     *
     * @return integer The exit status code
     *
     * @throws RuntimeException In case --enable-sigchild is activated and the sigchild compatibility mode is disabled
     *
     * @api
     */
    public function getExitCode();

    /**
     * Returns true if the child process has been terminated by an uncaught signal.
     *
     * It always returns false on Windows.
     *
     * @return Boolean
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     *
     * @api
     */
    public function hasBeenSignaled();

    /**
     * Returns the number of the signal that caused the child process to terminate its execution.
     *
     * It is only meaningful if hasBeenSignaled() returns true.
     *
     * @return integer
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     *
     * @api
     */
    public function getTermSignal();

    /**
     * Returns true if the child process has been stopped by a signal.
     *
     * It always returns false on Windows.
     *
     * @return Boolean
     *
     * @api
     */
    public function hasBeenStopped();

    /**
     * Returns the number of the signal that caused the child process to stop its execution.
     *
     * It is only meaningful if hasBeenStopped() returns true.
     *
     * @return integer
     *
     * @api
     */
    public function getStopSignal();

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param integer|float|null $timeout The timeout in seconds
     *
     * @return self The current Process instance
     *
     * @throws InvalidArgumentException if the timeout is negative
     */
    public function setTimeout($timeout);

    /**
     * Gets the process timeout.
     *
     * @return float|null The timeout in seconds or null if it's disabled
     */
    public function getTimeout();

    /**
     * Sets the process idle timeout.
     *
     * @param integer|float|null $timeout
     *
     * @return self The current Process instance.
     *
     * @throws InvalidArgumentException if the timeout is negative
     */
    public function setIdleTimeout($timeout);

    /**
     * Gets the process idle timeout.
     *
     * @return float|null
     */
    public function getIdleTimeout();
}
